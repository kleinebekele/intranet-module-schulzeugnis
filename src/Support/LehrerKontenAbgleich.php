<?php

namespace Intranet\Modules\Schulzeugnis\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulzeugnis\Models\Lehrer;
use Intranet\Modules\Schulzeugnis\Models\Protokoll;

/**
 * Verknüpft importierte Lehrer (ohne Konto) mit dem passenden Intranet-Benutzer –
 * ausschließlich über die stabile externe ID (Lehrer.quell_id ↔ users.externe_id).
 *
 * Zwei Auslöser nutzen dieselbe Logik:
 *  - der tägliche Command {@see \Intranet\Modules\Schulzeugnis\Console\Commands\LehrerKontenVerknuepfen}
 *  - der Hook auf die User-Erstellung (sofortige Verknüpfung, siehe ServiceProvider).
 */
class LehrerKontenAbgleich
{
    /**
     * Alle noch nicht verknüpften Lehrer mit passender externer ID an DIESEN Benutzer
     * knüpfen. Gibt die Anzahl der neu verknüpften Lehrer zurück.
     */
    public static function fuerBenutzer(User $user, string $akteur = 'System (Konto-Anlage)'): int
    {
        $ext = trim((string) $user->externe_id);
        if ($ext === '') {
            return 0;
        }

        $offen = Lehrer::whereNull('core_user_id')->where('quell_id', $ext)->get();
        if ($offen->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($offen, $user, $ext, $akteur): void {
            foreach ($offen as $lehrer) {
                $lehrer->core_user_id = $user->id;
                $lehrer->save();

                Protokoll::log('lehrer_verknuepft', [
                    'schuljahr_id' => $lehrer->schuljahr_id,
                    'beschreibung' => "Lehrer {$lehrer->fullName()} automatisch mit Konto verknüpft (ExterneID {$ext} → #{$user->id}).",
                    'akteur_name'  => $akteur,
                ]);
            }
        });

        return $offen->count();
    }
}
