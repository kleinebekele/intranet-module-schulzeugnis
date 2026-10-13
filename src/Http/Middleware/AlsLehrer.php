<?php

namespace Intranet\Modules\Schulzeugnis\Http\Middleware;

use App\Models\User;
use App\Modules\Support\Modulzugriff;
use App\Support\Audit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * „Als Lehrer nutzen" (Zeugnisverwaltung): Ist in der Sitzung ein Lehrerkonto
 * gewählt, laufen die Zeugnisseiten so, als wäre diese Lehrkraft angemeldet –
 * Klassenräume, ToDos, Rechte an Texten, Menüpunkte … genau wie bei ihr.
 *
 * - Nur auf den Routen dieses Moduls (die Modul-Zugriffsprüfung des Cores lief
 *   davor noch mit dem echten Konto). Außerhalb bleibt man man selbst.
 * - Wer es nicht (mehr) darf, verliert die Auswahl stillschweigend.
 * - Jede Änderung (alles außer GET) landet im Audit-Log: Akteur = echtes Konto,
 *   Betroffener = die Lehrkraft. Die Erfolgsmeldung nennt die Lehrkraft.
 *
 * Vorbild: AlsPerson im Kantinen-Modul.
 */
class AlsLehrer
{
    public const SITZUNG = 'schulzeugnis.als_lehrer';

    /** Darf hier nie umgeschaltet werden: Start und Ausstieg selbst. */
    private const OHNE = ['module.schulzeugnis.lehrer.als', 'module.schulzeugnis.lehrer.als.ende'];

    public function handle(Request $request, Closure $next): Response
    {
        $echt = $request->user();
        $id = $request->session()->get(self::SITZUNG);
        if (! $echt || ! $id || in_array($request->route()?->getName(), self::OHNE, true)) {
            return $next($request);
        }

        $ziel = User::find($id);
        if (! $ziel || ! self::darf($echt) || ! self::zielErlaubt($echt, $ziel)) {
            $request->session()->forget(self::SITZUNG);

            return $next($request);
        }

        Auth::setUser($ziel);
        $request->setUserResolver(fn () => $ziel);
        $request->attributes->set('schulzeugnis_echter_user', $echt);

        try {
            $antwort = $next($request);
        } finally {
            Auth::setUser($echt);
            $request->setUserResolver(fn () => $echt);
        }

        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) && $antwort->getStatusCode() < 400) {
            $route = (string) $request->route()?->getName();
            Audit::schreiben(
                'schulzeugnis.als_lehrer_aenderung',
                "Als {$ziel->name}: ".str_replace('module.schulzeugnis.', '', $route),
                betroffener: $ziel,
                daten: [
                    'route' => $route,
                    'parameter' => collect($request->route()?->parameters() ?? [])->map(fn ($p) => is_object($p) ? ($p->getKey() ?? (string) $p) : $p)->all(),
                ],
                akteur: $echt,
            );
            if ($request->session()->has('status')) {
                $request->session()->flash('status', "Für {$ziel->name}: ".$request->session()->get('status'));
            }
        }

        return $antwort;
    }

    /** Darf $user als Lehrkraft nutzen? Wer die Lehrer verwalten darf (Admins immer). */
    public static function darf(User $user): bool
    {
        return app(Modulzugriff::class)->darfRoute('module.schulzeugnis.lehrer.als', $user);
    }

    /**
     * Darf $echt in die Rolle von $ziel schlüpfen? Admin-Konten und Zeugnisadmins
     * nur, wenn $echt selbst Administrator ist – sonst hebt „als" die eigenen
     * Rechte an (die Modul-Prüfungen in den Controllern laufen gegen das Ziel).
     * Wird bei jeder Anfrage erneut geprüft, nicht nur beim Start.
     */
    public static function zielErlaubt(User $echt, User $ziel): bool
    {
        if ($echt->is_admin) {
            return true;
        }

        return ! $ziel->is_admin
            && ! $ziel->roles()->where('roles.role_id', 'zeugnis_admin')->exists();
    }

    /** Das gerade gewählte Konto (für den Balken), sonst null. */
    public static function person(): ?User
    {
        $id = session(self::SITZUNG);

        return $id ? User::find($id) : null;
    }
}
