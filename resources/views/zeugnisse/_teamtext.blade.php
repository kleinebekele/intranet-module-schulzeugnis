{{-- Team-Teaching (mehrere Fachlehrer, siehe Support\Teamtext).
     Erwartet: $team (modus gruppe|allein|ansicht, mein, mitglieder, andere, gruppe), $readonly, $rows.
     Geschrieben wird immer der eigene Teiltext (name="teiltext") – in der Gruppe ist das der
     gemeinsame Text, den der Server an alle Mitglieder verteilt. --}}
@php
    $modus      = $team['modus'];
    // Wofür gilt die Entscheidung? Schülertext → alle Schüler des Fachs; Klassentext → nur er.
    $geltung    = ($bereich ?? 'schueler') === 'klasse'
        ? 'nur für den klassenweiten Text (die Schülertexte entscheidet ihr getrennt)'
        : 'für die Schülertexte aller Schüler dieses Fachs (den klassenweiten Text entscheidet ihr getrennt)';
    $mein       = $team['mein'];
    $kannSelbst = $mein && ! $readonly;
    $inGruppe   = $modus === 'gruppe';
    $namen      = fn ($c) => $c->pluck('autor_name')->filter()->implode(', ');
    // Gruppe, der ich nicht angehöre (mind. zwei andere) bzw. einzelne Zustimmende
    $fremdeGruppe = $inGruppe ? collect() : $team['gruppe'];
    $einzelne     = $team['andere']->reject(fn ($t) => $fremdeGruppe->contains('id', $t->id))->values();
    $wartende     = $kannSelbst && ! $inGruppe
        ? ($fremdeGruppe->isNotEmpty() ? $fremdeGruppe : $einzelne->filter(fn ($t) => $t->zusammenfassen))
        : collect();
@endphp
<input type="hidden" name="team_modus" value="{{ $modus }}">
{{-- 1 = zustimmen/beitreten, speichern, auf dieser Seite bleiben. --}}
<input type="hidden" name="zusammenfassen_jetzt" value="0" class="zt-team-jetzt">
{{-- 1 = nur mich aus der Zusammenfassung lösen, speichern, auf dieser Seite bleiben. --}}
<input type="hidden" name="trennen_jetzt" value="0" class="zt-team-trennen">

<div class="zt-team rounded-lg border border-indigo-200 bg-indigo-50/40 p-3">
    <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-indigo-600">
        <i class="bx bx-group text-sm"></i>
        {{ $inGruppe ? 'Gemeinsamer Text mit '.$namen($team['mitglieder']) : 'Mehrere Fachlehrer – jeder schreibt zunächst seinen Teil' }}
    </p>

    @if ($mein)
        <label class="mt-2 block text-sm font-medium text-gray-700">{{ $inGruppe ? 'Gemeinsamer Text' : 'Mein Text' }}
            <textarea name="teiltext" rows="{{ $rows }}" data-fmt @disabled(! $kannSelbst)
                      class="zt-txt-area mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      placeholder="{{ $inGruppe ? 'Gemeinsamer Text …' : 'Dein Teil des Textes …' }}">{{ old('teiltext', $mein->inhalt) }}</textarea>
        </label>
    @else
        <p class="mt-2 text-sm text-gray-600">Jede Lehrkraft schreibt ihren eigenen Teil bzw. den gemeinsamen Text ihrer Gruppe. Bearbeiten lässt er sich nur von den Fachlehrern selbst.</p>
    @endif

    @if ($fremdeGruppe->isNotEmpty())
        <div class="zt-team-fremd mt-3 rounded-lg border border-gray-200 p-3">
            <p class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                <span class="font-medium text-gray-600">Gemeinsamer Text von {{ $namen($fremdeGruppe) }}</span>
                <span class="rounded-full bg-green-100 px-2 py-0.5 text-green-800">zusammengefasst</span>
            </p>
            <div class="zt-team-text mt-1 text-sm">{{ trim((string) $fremdeGruppe->first()->inhalt) !== '' ? $fremdeGruppe->first()->inhalt : '— noch kein Text —' }}</div>
        </div>
    @endif
    @foreach ($einzelne as $t)
        <div class="zt-team-fremd mt-3 rounded-lg border border-gray-200 p-3">
            <p class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                <span class="font-medium text-gray-600">Text von {{ $t->autor_name ?: 'Lehrkraft' }}</span>
                @if ($t->zusammenfassen)
                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-green-800">möchte zusammenfassen</span>
                @else
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-600">schreibt eigenen Text</span>
                @endif
            </p>
            <div class="zt-team-text mt-1 text-sm">{{ trim((string) $t->inhalt) !== '' ? $t->inhalt : '— noch kein Text —' }}</div>
        </div>
    @endforeach

    @if ($wartende->isNotEmpty())
        {{-- Andere wollen (schon) zusammenfassen, ich noch nicht → deutlicher Hinweis + Knopf. --}}
        <div class="zt-team-wartet mt-3 flex items-start gap-2 rounded-lg p-3 text-sm">
            <i class="bx bxs-bell-ring mt-0.5 text-lg"></i>
            <div>
                <strong>
                    {{ $namen($wartende) }}
                    {{ $fremdeGruppe->isNotEmpty() ? 'haben ihre Texte zusammengefasst.' : ($wartende->count() === 1 ? 'möchte' : 'möchten').' die Texte zusammenfassen.' }}
                </strong>
                <span class="block">Mit dem Knopf speicherst du deinen Text, fasst zusammen und bleibst hier – danach schreibst du im gemeinsamen Text mit. Das gilt {{ $geltung }}.</span>
                <button type="button" class="zt-team-jetzt-knopf mt-2 inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium">
                    <i class="bx bx-merge"></i> {{ $fremdeGruppe->isNotEmpty() ? 'Meinen Text dazunehmen & speichern' : 'Jetzt zusammenfassen & speichern' }}
                </button>
            </div>
        </div>
    @endif

    @if ($kannSelbst && $inGruppe)
        {{-- In der Gruppe: Zustimmung bleibt; herauslösen nur über den eigenen Knopf (mit Rückfrage), betrifft nur mich. --}}
        <input type="hidden" name="zusammenfassen" value="1">
        @php $bleiben = $team['mitglieder']->count() >= 2; @endphp
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <button type="button" class="zt-team-trennen-knopf inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium"
                    data-frage="Deinen Text aus der Zusammenfassung lösen – {{ $geltung }}? Du behältst jeweils den bisherigen gemeinsamen Text als eigenen Text{{ $bleiben ? ' – '.$namen($team['mitglieder']).' bleiben zusammengefasst' : '' }}.">
                <i class="bx bx-unlink"></i> Meinen Text herauslösen &amp; speichern
            </button>
            @if ($bleiben)
                <span class="text-xs text-gray-500">Betrifft nur dich – {{ $namen($team['mitglieder']) }} bleiben zusammengefasst.</span>
            @endif
        </div>
    @elseif ($kannSelbst && $wartende->isEmpty())
        {{-- Allein, niemand wartet auf mich: Zustimmung vormerken. --}}
        <label class="mt-3 flex items-start gap-2">
            <input type="checkbox" name="zusammenfassen" value="1"
                   @checked(old('zusammenfassen', $mein->zusammenfassen))
                   class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            <span class="text-sm text-gray-700">
                <span class="font-medium">Texte zusammenfassen</span>
                <span class="block text-xs text-gray-500">Gilt {{ $geltung }}. Sobald eine weitere Lehrkraft zustimmt, entsteht je Text ein gemeinsamer Text; bis dahin stehen die Texte auf dem Zeugnis nacheinander.</span>
            </span>
        </label>
    @endif
</div>
<style>
    .zt-team-fremd { background: var(--color-gray-50, #f9fafb); }
    .zt-team-text { white-space: pre-wrap; color: var(--color-gray-400, #9ca3af); }
    .zt-team-wartet {
        background: var(--color-amber-50, #fffbeb);
        border: 2px solid var(--color-amber-400, #fbbf24);
        color: var(--color-amber-900, #78350f);
    }
    .zt-team-wartet i { color: var(--color-amber-500, #f59e0b); }
    .zt-team-jetzt-knopf { background: var(--color-amber-500, #f59e0b); color: #fff; }
    .zt-team-jetzt-knopf:hover { background: var(--color-amber-600, #d97706); }
    .zt-team-trennen-knopf { border-color: var(--color-gray-300, #d1d5db); color: var(--color-gray-700, #374151); background: var(--color-white, #fff); }
    .zt-team-trennen-knopf:hover { background: var(--color-red-50, #fef2f2); color: var(--color-red-700, #b91c1c); }
</style>
<script>
    (function () {
        document.querySelectorAll('.zt-team-jetzt-knopf').forEach(function (knopf) {
            if (knopf.dataset.gebunden) { return; }
            knopf.dataset.gebunden = '1';
            knopf.addEventListener('click', function () {
                var form = knopf.closest('form');
                if (!form) { return; }
                form.querySelector('.zt-team-jetzt').value = '1';
                var cb = form.querySelector('input[name="zusammenfassen"]');
                if (cb) { cb.checked = true; }
                // Nicht weiterblättern – das Ergebnis soll direkt hier zu sehen sein.
                form.querySelectorAll('input[name="weiter"]').forEach(function (r) { r.checked = false; });
                form.requestSubmit ? form.requestSubmit() : form.submit();
            });
        });
        document.querySelectorAll('.zt-team-trennen-knopf').forEach(function (knopf) {
            if (knopf.dataset.gebunden) { return; }
            knopf.dataset.gebunden = '1';
            knopf.addEventListener('click', function () {
                var form = knopf.closest('form');
                if (!form) { return; }
                var frage = knopf.dataset.frage || 'Deinen Text aus der Zusammenfassung lösen?';
                var frag = window.bestaetige
                    ? window.bestaetige(frage, { titel: 'Text herauslösen', knopf: 'Herauslösen & speichern' })
                    : Promise.resolve(window.confirm(frage));
                frag.then(function (ja) {
                    if (!ja) { return; }
                    form.querySelector('.zt-team-trennen').value = '1';
                    form.querySelectorAll('input[name="weiter"]').forEach(function (r) { r.checked = false; });
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                });
            });
        });
    })();
</script>
