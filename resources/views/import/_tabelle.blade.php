{{-- Generische Darstellung einer Import-Analyse (Vorschau wie Ergebnis).
     Erwartet $analyse = [
        'spalten_titel' => ['Name', ...],                     Kopf der Datenspalten
        'zeilen'        => [['zeile','status','zellen'=>[],'hinweis'], ...],
        'zaehl'         => ['neu','aktualisiert','unveraendert','warnung','fehler'],
        'infos'         => [['label','items'=>[],'ton'=>'grau|amber'], ...],  optional
     ] --}}
@php
    $zaehl = $analyse['zaehl'];
    $badges = [
        'neu'          => ['Neu', 'bg-green-100 text-green-800 ring-green-200'],
        'aktualisiert' => ['Aktualisiert', 'bg-blue-100 text-blue-800 ring-blue-200'],
        'unveraendert' => ['Unverändert', 'bg-gray-100 text-gray-600 ring-gray-200'],
        'warnung'      => ['Übersprungen', 'bg-amber-100 text-amber-800 ring-amber-200'],
        'fehler'       => ['Fehler', 'bg-red-100 text-red-800 ring-red-200'],
    ];
    $zeilenBadge = [
        'neu'          => 'bg-green-100 text-green-800',
        'aktualisiert' => 'bg-blue-100 text-blue-800',
        'unveraendert' => 'bg-gray-100 text-gray-600',
        'warnung'      => 'bg-amber-100 text-amber-800',
        'fehler'       => 'bg-red-100 text-red-800',
    ];
    $spalten = $analyse['spalten_titel'] ?? [];
@endphp

{{-- Die Zähler sind zugleich Filter: Klick zeigt nur Zeilen dieses Status, erneuter Klick alle. --}}
<div class="imp-filter flex flex-wrap items-center gap-2">
    @foreach ($badges as $key => [$label, $klasse])
        <button type="button" data-filter="{{ $key }}" aria-pressed="false" @disabled($zaehl[$key] === 0)
                title="{{ $zaehl[$key] === 0 ? 'Keine Zeilen' : 'Nur „'.$label.'" anzeigen' }}"
                class="imp-filter-knopf inline-flex items-center gap-1 rounded-full px-3 py-1 text-sm font-medium ring-1 {{ $klasse }}">
            {{ $label }}: {{ $zaehl[$key] }}
        </button>
    @endforeach
    <button type="button" data-filter="" class="imp-filter-alle text-sm text-gray-500 underline hover:text-gray-700" hidden>Alle zeigen</button>
</div>

<div class="imp-tabelle overflow-x-auto rounded-xl border border-gray-200 bg-white">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-400">
                <th class="px-4 py-2 font-medium">Zeile</th>
                <th class="px-4 py-2 font-medium">Status</th>
                @foreach ($spalten as $titel)
                    <th class="px-4 py-2 font-medium">{{ $titel }}</th>
                @endforeach
                <th class="px-4 py-2 font-medium">Hinweis</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($analyse['zeilen'] as $r)
                <tr class="border-b border-gray-100 last:border-0" data-status="{{ $r['status'] }}">
                    <td class="px-4 py-2 text-gray-400">{{ $r['zeile'] }}</td>
                    <td class="px-4 py-2">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $zeilenBadge[$r['status']] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ $badges[$r['status']][0] ?? $r['status'] }}
                        </span>
                    </td>
                    @foreach ($r['zellen'] as $i => $zelle)
                        <td class="px-4 py-2 {{ $i === 0 ? 'font-medium text-gray-800' : 'text-gray-600' }}">{{ $zelle }}</td>
                    @endforeach
                    <td class="px-4 py-2 text-gray-600">{{ $r['hinweis'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="imp-leer px-4 py-3 text-sm text-gray-400" hidden>Keine Zeilen mit diesem Status.</p>
</div>

<style>
    .imp-filter-knopf { cursor: pointer; transition: opacity .15s, box-shadow .15s; }
    .imp-filter-knopf:disabled { cursor: default; opacity: .45; }
    .imp-filter.gefiltert .imp-filter-knopf[aria-pressed="false"] { opacity: .45; }
    .imp-filter-knopf[aria-pressed="true"] { box-shadow: 0 0 0 2px currentColor; }
    .imp-filter-alle[hidden], .imp-leer[hidden] { display: none; }
</style>
<script>
    (function () {
        var leiste = document.currentScript.previousElementSibling;
        while (leiste && !leiste.classList.contains('imp-filter')) { leiste = leiste.previousElementSibling; }
        if (!leiste) { return; }
        var tabelle = leiste.nextElementSibling, alle = leiste.querySelector('.imp-filter-alle');
        var zeilen = tabelle.querySelectorAll('tbody tr[data-status]'), leer = tabelle.querySelector('.imp-leer');

        function filtern(status) {
            var sichtbar = 0;
            zeilen.forEach(function (tr) {
                var zeigen = !status || tr.dataset.status === status;
                tr.hidden = !zeigen;
                if (zeigen) { sichtbar++; }
            });
            leiste.querySelectorAll('.imp-filter-knopf').forEach(function (k) {
                k.setAttribute('aria-pressed', String(!!status && k.dataset.filter === status));
            });
            leiste.classList.toggle('gefiltert', !!status);
            alle.hidden = !status;
            leer.hidden = sichtbar > 0;
        }

        leiste.addEventListener('click', function (e) {
            var k = e.target.closest('button[data-filter]');
            if (!k || k.disabled) { return; }
            var aktiv = k.getAttribute('aria-pressed') === 'true';
            filtern(aktiv ? '' : k.dataset.filter);
        });
    })();
</script>

@foreach ($analyse['infos'] ?? [] as $info)
    @php $verstecken = ($nurImportierte ?? false) && ($info['nur_ergebnis'] ?? false); @endphp
    @if (! empty($info['items']) && ! $verstecken)
        @php $amber = ($info['ton'] ?? 'grau') === 'amber'; @endphp
        <div class="rounded-xl border p-4 text-sm {{ $amber ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-gray-200 bg-gray-50 text-gray-600' }}">
            <p class="font-medium {{ $amber ? 'text-amber-800' : 'text-gray-700' }}">{{ count($info['items']) }} · {{ $info['label'] }}</p>
            <p class="mt-1">{{ implode(' · ', $info['items']) }}</p>
        </div>
    @endif
@endforeach
