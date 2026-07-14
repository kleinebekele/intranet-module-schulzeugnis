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

<div class="flex flex-wrap gap-2">
    @foreach ($badges as $key => [$label, $klasse])
        <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-sm font-medium ring-1 {{ $klasse }}">
            {{ $label }}: {{ $zaehl[$key] }}
        </span>
    @endforeach
</div>

<div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
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
                <tr class="border-b border-gray-100 last:border-0">
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
</div>

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
