<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Intranet\Modules\Schulzeugnis\Http\Middleware\AlsLehrer;
use Intranet\Modules\Schulzeugnis\Models\Lehrer;

/** „Als Lehrer nutzen" starten und beenden – siehe {@see AlsLehrer}. */
class AlsLehrerController
{
    public function start(Request $request, Lehrer $lehrer)
    {
        abort_unless(AlsLehrer::darf($request->user()), 403, 'Nur die Zeugnisverwaltung darf als Lehrkraft nutzen.');
        $konto = $lehrer->core_user_id ? User::find($lehrer->core_user_id) : null;
        abort_unless($konto, 422, 'Diese Lehrkraft hat kein Intranet-Konto.');
        abort_if($konto->is($request->user()), 422, 'Das bist du selbst.');
        abort_unless(AlsLehrer::zielErlaubt($request->user(), $konto), 403, 'Administratoren und Zeugnisadmins kann nur ein Administrator übernehmen.');

        $request->session()->put(AlsLehrer::SITZUNG, $konto->id);
        Audit::schreiben('schulzeugnis.als_lehrer_start', "Nutzt das Zeugnis-Modul als {$konto->name}", betroffener: $konto);

        return redirect()->route('module.schulzeugnis.klassenraeume.index')
            ->with('status', "Du siehst das Zeugnis-Modul jetzt als {$konto->name}. Alles, was du hier änderst, gilt für diese Person.");
    }

    public function ende(Request $request)
    {
        $person = AlsLehrer::person();
        $request->session()->forget(AlsLehrer::SITZUNG);
        if ($person) {
            Audit::schreiben('schulzeugnis.als_lehrer_ende', "Nutzt das Zeugnis-Modul nicht mehr als {$person->name}", betroffener: $person);
        }

        return redirect()->route('module.schulzeugnis.lehrer.index')
            ->with('status', $person ? "Du bist wieder du selbst (nicht mehr {$person->name})." : 'Du bist wieder du selbst.');
    }
}
