<?php

namespace Intranet\Modules\Schulzeugnis\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulzeugnis\Models\Lehrer;
use Intranet\Modules\Schulzeugnis\Models\Protokoll;

/**
 * Täglicher Abgleich: verknüpft importierte Lehrer, die noch kein Intranet-Konto
 * hatten, sobald ein Konto mit passender externer ID existiert.
 *
 * Match AUSSCHLIESSLICH über die stabile externe ID (Lehrer.quell_id → users.externe_id),
 * nie über Name oder E-Mail. So ist ein Lehrer spätestens ~24 h nach Kontoerstellung
 * verknüpft, ohne dass jemand von Hand nacharbeiten muss.
 */
class LehrerKontenVerknuepfen extends Command
{
    protected $signature = 'schulzeugnis:lehrer-verknuepfen
        {--dry-run : Nur anzeigen, was verknüpft würde – nichts schreiben}';

    protected $description = 'Verknüpft importierte Lehrer ohne Konto anhand ihrer externen ID (quell_id → users.externe_id) mit dem passenden Intranet-Benutzer.';

    public function handle(): int
    {
        $trocken = (bool) $this->option('dry-run');

        // Kandidaten: noch nicht verknüpft, aber mit hinterlegter externer ID.
        $offen = Lehrer::whereNull('core_user_id')
            ->whereNotNull('quell_id')
            ->get();

        if ($offen->isEmpty()) {
            $this->info('Keine offenen Lehrer ohne Konto – nichts zu tun.');

            return self::SUCCESS;
        }

        // Alle betroffenen externen IDs in einer Abfrage zu Konten auflösen.
        $ids = $offen->pluck('quell_id')
            ->map(fn ($e) => trim((string) $e))
            ->filter()
            ->unique()
            ->values();

        $userNachExt = [];
        User::whereIn('externe_id', $ids->all())
            ->get(['id', 'name', 'externe_id'])
            ->each(function (User $u) use (&$userNachExt): void {
                $userNachExt[trim((string) $u->externe_id)] = $u;
            });

        $treffer = [];
        foreach ($offen as $lehrer) {
            $u = $userNachExt[trim((string) $lehrer->quell_id)] ?? null;
            if ($u) {
                $treffer[] = [$lehrer, $u];
            }
        }

        if ($treffer === []) {
            $this->info("{$offen->count()} Lehrer ohne Konto – aber zu keiner externen ID existiert (noch) ein Benutzer.");

            return self::SUCCESS;
        }

        if ($trocken) {
            $this->warn('Trockenlauf – es wird nichts geschrieben.');
            $this->table(['Lehrer', 'Schuljahr', 'ExterneID', 'Konto'], array_map(
                fn ($t) => [$t[0]->fullName(), $t[0]->schuljahr_id, $t[0]->quell_id, $t[1]->name . " (#{$t[1]->id})"],
                $treffer
            ));
            $this->info(count($treffer) . ' von ' . $offen->count() . ' würden verknüpft.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($treffer): void {
            foreach ($treffer as [$lehrer, $u]) {
                $lehrer->core_user_id = $u->id;
                $lehrer->save();

                Protokoll::log('lehrer_verknuepft', [
                    'schuljahr_id' => $lehrer->schuljahr_id,
                    'beschreibung' => "Lehrer {$lehrer->fullName()} automatisch mit Konto verknüpft (ExterneID {$lehrer->quell_id} → #{$u->id}).",
                    'akteur_name'  => 'System (täglicher Abgleich)',
                ]);
            }
        });

        $this->info(count($treffer) . ' von ' . $offen->count() . ' Lehrer automatisch verknüpft.');

        return self::SUCCESS;
    }
}
