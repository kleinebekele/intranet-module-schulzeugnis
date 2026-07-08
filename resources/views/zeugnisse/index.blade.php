@php
    $farbeKlasse = [
        'gray'  => 'text-gray-300',
        'amber' => 'text-amber-500',
        'red'   => 'text-red-500',
        'green' => 'text-green-600',
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="book" class="text-2xl text-indigo-600" />
            <div>
                <h1 class="text-xl font-semibold text-gray-800">Zeugnisse</h1>
                <p class="text-sm text-gray-500">Klasse {{ $klasse->name }} &middot; Schuljahr {{ $klasse->schuljahr->name }} &middot; {{ $schueler->count() }} Schüler</p>
            </div>
        </div>
    </x-slot>

    <style>
        #zt-table td, #zt-table th { padding-top: 1px; padding-bottom: 1px; }
        #zt-table i.bx { line-height: 1; vertical-align: middle; }
        #zt-table tbody a { padding: 0; }
        .zt-hidden-col { display: none !important; }
        .zt-chip { border: 1px solid #d1d5db; border-radius: 9999px; padding: 2px 10px; font-size: 12px; color: #374151; background: #fff; cursor: pointer; }
        .zt-chip:hover { background: #f3f4f6; }
        .zt-chip.zt-off { opacity: .45; text-decoration: line-through; }
    </style>

    <div class="space-y-3">
        <a href="{{ route('module.schulzeugnis.klassen.index', $klasse->schuljahr_id) }}"
           class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
            &larr; Zurück zu den Klassen
        </a>

        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ session('error') }}</div>
        @endif

        {{-- Filterleiste --}}
        <div class="space-y-2 rounded-xl border border-gray-200 bg-white p-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Spalten</span>
                <button type="button" id="zt-mine" class="zt-chip" style="border-color:#a5b4fc;color:#4f46e5;">Meine Fächer</button>
                <button type="button" id="zt-all" class="zt-chip">Alle</button>
                <span class="mx-1 text-gray-300">|</span>
                <button type="button" class="zt-chip" data-col="haupt">Haupt</button>
                @foreach ($faecher as $fach)
                    <button type="button" class="zt-chip" data-col="{{ $fach->id }}" title="{{ $fach->name }}">{{ $fach->kuerzel ?: $fach->name }}</button>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Zeilen nach Status</span>
                <select id="zt-status" class="rounded-lg border-gray-300 py-1 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— alle —</option>
                    @foreach ($stati as $key => $meta)
                        <option value="{{ $key }}">{{ $meta['label'] }}</option>
                    @endforeach
                </select>
                <span id="zt-count" class="text-xs text-gray-400"></span>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white" style="max-height: 74vh; overflow: auto;">
            <table id="zt-table" class="min-w-full border-collapse text-sm">
                <thead>
                    <tr class="text-gray-600">
                        <th class="border-b border-r border-gray-200 px-4 text-left font-semibold" style="position: sticky; left: 0; top: 0; z-index: 30; background: #f9fafb;">Schüler</th>
                        <th class="zt-col zt-col-haupt border-b border-gray-200 px-2 text-center font-semibold" style="position: sticky; top: 0; z-index: 20; background: #f9fafb;" title="Haupttext (Klassenlehrer)">Haupt</th>
                        @foreach ($faecher as $fach)
                            <th class="zt-col zt-col-{{ $fach->id }} border-b border-gray-200 px-2 text-center font-semibold" style="position: sticky; top: 0; z-index: 20; background: #f9fafb;" title="{{ $fach->name }}">{{ $fach->kuerzel ?: $fach->name }}</th>
                        @endforeach
                        <th class="border-b border-l border-gray-200 px-3 text-center font-semibold" style="position: sticky; top: 0; z-index: 20; background: #f9fafb;" title="Warnhinweis Textlänge">⚠</th>
                        <th class="border-b border-gray-200 px-3 text-center font-semibold" style="position: sticky; top: 0; z-index: 20; background: #f9fafb;">Vorschau</th>
                        <th class="border-b border-gray-200 px-3 text-center font-semibold" style="position: sticky; top: 0; z-index: 20; background: #f9fafb;">PDF</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($schueler as $s)
                        @php
                            $z = $s->zeugnis;
                            $abs = $z ? $z->abschnitte : collect();
                            $haupt = $abs->firstWhere('typ', 'haupttext');
                            $fachMap = $abs->whereIn('typ', ['fachtext', 'note'])->keyBy('fach_id');
                        @endphp
                        <tr class="border-b border-gray-100 hover:bg-indigo-50/40">
                            <td class="border-r border-gray-200 px-4 whitespace-nowrap" style="position: sticky; left: 0; z-index: 10; background: #fff;">
                                <span class="font-medium text-gray-800">{{ $s->nachname }}, {{ $s->vorname }}</span>
                                @if ($z && $z->istAbgeschlossen())
                                    <span class="ml-1 rounded-full bg-green-100 px-1.5 text-[10px] font-medium text-green-700">fertig</span>
                                @elseif (! $z)
                                    <form method="POST" action="{{ route('module.schulzeugnis.zeugnisse.store', [$klasse, $s]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="ml-1 text-xs text-indigo-600 hover:underline">+ anlegen</button>
                                    </form>
                                @endif
                            </td>

                            {{-- Haupttext --}}
                            <td class="zt-col zt-col-haupt px-2 text-center" data-status="{{ $haupt?->status ?? '' }}">
                                @if ($haupt)
                                    @php $m = $haupt->statusMeta(); @endphp
                                    <a href="{{ route('module.schulzeugnis.abschnitte.edit', $haupt) }}" title="Haupttext – {{ $m['label'] }}"
                                       class="inline-flex rounded p-0.5 hover:bg-indigo-100">
                                        <i class="bx {{ $m['icon'] }} text-lg {{ $farbeKlasse[$m['farbe']] ?? 'text-gray-300' }}"></i>
                                    </a>
                                @else
                                    <span class="text-gray-200">–</span>
                                @endif
                            </td>

                            {{-- Fächer --}}
                            @foreach ($faecher as $fach)
                                @php $a = $fachMap->get($fach->id); @endphp
                                <td class="zt-col zt-col-{{ $fach->id }} px-2 text-center" data-status="{{ $a?->status ?? '' }}">
                                    @if ($a)
                                        @php $m = $a->statusMeta(); @endphp
                                        <a href="{{ route('module.schulzeugnis.abschnitte.edit', $a) }}" title="{{ $fach->name }} – {{ $m['label'] }}"
                                           class="inline-flex rounded p-0.5 hover:bg-indigo-100">
                                            <i class="bx {{ $m['icon'] }} text-lg {{ $farbeKlasse[$m['farbe']] ?? 'text-gray-300' }}"></i>
                                        </a>
                                    @else
                                        <span class="text-gray-200">–</span>
                                    @endif
                                </td>
                            @endforeach

                            {{-- Warnhinweis Textlänge --}}
                            @php $w = $warnungen[$s->id] ?? null; @endphp
                            <td class="border-l border-gray-200 px-3 text-center whitespace-nowrap">
                                @if ($w && $w['status'] === 'verkleinert')
                                    <span class="text-amber-600" title="Text zu lang – passt automatisch verkleinert bei {{ $w['passtBei'] }} pt">
                                        <i class="bx bx-error-circle"></i> {{ $w['passtBei'] }} pt
                                    </span>
                                @elseif ($w && $w['status'] === 'ueberlauf')
                                    <span class="text-red-600" title="Text passt auch bei kleinster Schrift nicht vollständig">
                                        <i class="bx bxs-error"></i> zu lang
                                    </span>
                                @elseif ($w && $w['status'] === 'ok')
                                    <i class="bx bx-check text-green-500" title="passt"></i>
                                @else
                                    <span class="text-gray-200">–</span>
                                @endif
                            </td>

                            {{-- Vorschau / PDF --}}
                            <td class="px-3 text-center">
                                @if ($z)
                                    <a href="{{ route('module.schulzeugnis.zeugnisse.vorschau', $z) }}" target="_blank" title="Vorschau (HTML)" class="inline-flex text-indigo-600 hover:text-indigo-800">
                                        <i class="bx bx-show text-lg"></i>
                                    </a>
                                @else
                                    <span class="text-gray-200">–</span>
                                @endif
                            </td>
                            <td class="px-3 text-center">
                                @if ($z)
                                    <a href="{{ route('module.schulzeugnis.zeugnisse.pdf', $z) }}" target="_blank" title="Als PDF herunterladen" class="inline-flex text-red-600 hover:text-red-800">
                                        <i class="bx bxs-file-pdf text-lg"></i>
                                    </a>
                                @else
                                    <span class="text-gray-200">–</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $faecher->count() + 5 }}" class="px-4 py-8 text-center text-gray-500">
                                Noch keine Schüler in dieser Klasse.
                                <a href="{{ route('module.schulzeugnis.schueler.index', $klasse->schuljahr_id) }}" class="text-indigo-600 hover:text-indigo-700">Schüler anlegen</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        (function () {
            const table = document.getElementById('zt-table');
            const statusSel = document.getElementById('zt-status');
            const countEl = document.getElementById('zt-count');
            const mineIds = @json(array_map('strval', $meineFachIds));
            const mineHaupt = @json($binKlassenlehrer);

            function setCol(key, show) {
                document.querySelectorAll('.zt-col-' + key).forEach((el) => el.classList.toggle('zt-hidden-col', !show));
                const chip = document.querySelector('.zt-chip[data-col="' + key + '"]');
                if (chip) chip.classList.toggle('zt-off', !show);
            }

            function applyRows() {
                const st = statusSel.value;
                let sichtbar = 0;
                table.querySelectorAll('tbody tr').forEach((tr) => {
                    let show = true;
                    if (st) {
                        show = [...tr.querySelectorAll('td[data-status]')].some((td) =>
                            td.dataset.status === st && !td.classList.contains('zt-hidden-col'));
                    }
                    tr.style.display = show ? '' : 'none';
                    if (show) sichtbar++;
                });
                countEl.textContent = st ? (sichtbar + ' Schüler mit diesem Status') : '';
            }

            document.querySelectorAll('.zt-chip[data-col]').forEach((chip) => {
                chip.addEventListener('click', () => { setCol(chip.dataset.col, chip.classList.contains('zt-off')); applyRows(); });
            });
            document.getElementById('zt-mine').addEventListener('click', () => {
                setCol('haupt', mineHaupt);
                @foreach ($faecher as $fach)
                    setCol('{{ $fach->id }}', mineIds.includes('{{ $fach->id }}'));
                @endforeach
                applyRows();
            });
            document.getElementById('zt-all').addEventListener('click', () => {
                setCol('haupt', true);
                @foreach ($faecher as $fach)
                    setCol('{{ $fach->id }}', true);
                @endforeach
                applyRows();
            });
            statusSel.addEventListener('change', applyRows);

            // Für Lehrer: standardmäßig auf die eigenen Fächer filtern; Admins sehen alles.
            if (mineIds.length || mineHaupt) {
                document.getElementById('zt-mine').click();
            }
        })();
    </script>
</x-app-layout>
