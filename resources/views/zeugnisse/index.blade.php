@php
    $farbeKlasse = [
        'gray'  => 'text-gray-300',
        'amber' => 'text-amber-500',
        'red'   => 'text-red-500',
        'green' => 'text-green-600',
    ];

    // Titelzeile in der Stufenfarbe (wie die Türen in den Klassenräumen).
    // Textfarbe je nach Helligkeit der Grundfarbe automatisch hell/dunkel wählen,
    // damit jede frei gewählte Stufenfarbe lesbar bleibt.
    $stufe     = $klasse->stufe;
    $kopfFarbe = $stufe?->farbe ?: '#64748b';
    $hex       = ltrim($kopfFarbe, '#');
    $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
    $helligkeit  = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    $weisseSchrift = $helligkeit < 0.5; // dunkle Fläche → weiße Schrift, sonst dunkle
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="zi-kopf {{ $weisseSchrift ? 'zi-kopf-weiss' : 'zi-kopf-schwarz' }}"
             style="--kr: {{ $kopfFarbe }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <x-module-icon name="book" class="zi-kopf-icon text-2xl" />
                    <div>
                        <h1 class="zi-kopf-titel text-xl font-semibold">Zeugnisse</h1>
                        <p class="zi-kopf-sub text-sm">Klasse {{ $klasse->name }} &middot; Schuljahr {{ $klasse->schuljahr->name }} &middot; {{ $schueler->count() }} Schüler @if ($stufe) &middot; {{ $stufe->name }} @endif</p>
                    </div>
                </div>

                <div class="zi-kopf-box flex items-center gap-2 rounded-xl px-3 py-1.5">
                    <i class="bx bxs-user-circle zi-kopf-icon text-3xl"></i>
                    <div class="leading-tight">
                        <div class="zi-kopf-boxlabel text-xs font-semibold uppercase tracking-wide">Klassenlehrer</div>
                        @if ($klasse->klassenlehrer)
                            <div class="zi-kopf-titel text-sm font-semibold">{{ $klasse->klassenlehrer->fullName() }}</div>
                        @else
                            <div class="zi-kopf-sub text-sm">— nicht gesetzt —</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <style>
        /* Titelzeile in der Stufenfarbe (gleiche Grundfarbe wie die Klassenraum-Tür).
           Vollflächig bis an die Kanten – die negativen Ränder heben das Padding des
           Core-Headers (px-4/sm:px-6/lg:px-8, py-5) auf. Bewusst als eigenes CSS, weil
           die negativen Tailwind-Margin-Utilities in dieser Instanz nicht kompiliert sind. */
        .zi-kopf {
            margin: -1.25rem -1rem;
            padding: 1.25rem 1rem;
            background: linear-gradient(135deg, var(--kr), color-mix(in srgb, var(--kr) 78%, black));
        }
        @media (min-width: 640px) {
            .zi-kopf { margin-left: -1.5rem; margin-right: -1.5rem; padding-left: 1.5rem; padding-right: 1.5rem; }
        }
        @media (min-width: 1024px) {
            .zi-kopf { margin-left: -2rem; margin-right: -2rem; padding-left: 2rem; padding-right: 2rem; }
        }
        .zi-kopf-weiss .zi-kopf-titel  { color: #fff; }
        .zi-kopf-weiss .zi-kopf-sub    { color: rgba(255,255,255,.82); }
        .zi-kopf-weiss .zi-kopf-icon   { color: rgba(255,255,255,.92); }
        .zi-kopf-weiss .zi-kopf-box    { background: rgba(255,255,255,.15); box-shadow: inset 0 0 0 1px rgba(255,255,255,.28); }
        .zi-kopf-weiss .zi-kopf-boxlabel { color: rgba(255,255,255,.72); }

        .zi-kopf-schwarz .zi-kopf-titel  { color: #1f2937; }
        .zi-kopf-schwarz .zi-kopf-sub    { color: rgba(31,41,55,.75); }
        .zi-kopf-schwarz .zi-kopf-icon   { color: rgba(31,41,55,.85); }
        .zi-kopf-schwarz .zi-kopf-box    { background: rgba(255,255,255,.35); box-shadow: inset 0 0 0 1px rgba(31,41,55,.15); }
        .zi-kopf-schwarz .zi-kopf-boxlabel { color: rgba(31,41,55,.6); }

        #zt-table td, #zt-table th { padding-top: 1px; padding-bottom: 1px; }
        #zt-table i.bx { line-height: 1; vertical-align: middle; }
        #zt-table tbody a { padding: 0; }
        .zt-hidden-col { display: none !important; }
        .zt-chip { border: 1px solid #d1d5db; border-radius: 9999px; padding: 2px 10px; font-size: 12px; color: #374151; background: #fff; cursor: pointer; }
        .zt-chip:hover { background: #f3f4f6; }
        .zt-chip.zt-off { opacity: .45; text-decoration: line-through; }
        #zt-table th.zt-kopf { cursor: help; }
        #zt-tip {
            position: fixed; z-index: 60; max-width: 300px;
            background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
            box-shadow: 0 12px 30px -8px rgba(0,0,0,.35);
            padding: 10px 12px; font-size: 12px; color: #374151;
            pointer-events: none; opacity: 0; transform: translateY(4px);
            transition: opacity .12s ease, transform .12s ease;
        }
        #zt-tip.zt-show { opacity: 1; transform: translateY(0); pointer-events: auto; }
        #zt-tip .zt-tip-fach { font-weight: 700; color: #4f46e5; font-size: 13px; margin-bottom: 2px; }
        #zt-tip .zt-tip-label { text-transform: uppercase; letter-spacing: .04em; font-size: 10px; font-weight: 600; color: #9ca3af; margin-top: 7px; }
        #zt-tip .zt-tip-text { white-space: pre-wrap; color: #4b5563; margin-top: 1px; line-height: 1.35; }
        #zt-tip .zt-tip-muted { color: #9ca3af; font-style: italic; }
        #zt-tip .zt-tip-link {
            display: inline-flex; align-items: center; gap: 4px; margin-top: 10px;
            font-weight: 600; color: #4f46e5; text-decoration: none;
        }
        #zt-tip .zt-tip-link:hover { color: #4338ca; text-decoration: underline; }
    </style>

    <div class="space-y-3">
        <a href="{{ route('module.schulzeugnis.klassenraeume.index') }}"
           class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
            &larr; Zurück zu den Klassenräumen
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
                        <th class="zt-col zt-col-haupt zt-kopf border-b border-gray-200 px-2 text-center font-semibold"
                            style="position: sticky; top: 0; z-index: 20; background: #f9fafb;"
                            data-fach="Haupttext" data-rolle="Klassenlehrer"
                            data-lehrer="{{ $klasse->klassenlehrer?->fullName() }}"
                            data-klassentext="{{ $klassentexte['haupt'] ?? '' }}"
                            @if ($istAdmin || $binKlassenlehrer) data-editurl="{{ route('module.schulzeugnis.klassenraeume.klassentexte.edit', ['klasse' => $klasse, 'fach' => 'haupt']) }}" @endif>Haupt</th>
                        @foreach ($faecher as $fach)
                            <th class="zt-col zt-col-{{ $fach->id }} zt-kopf border-b border-gray-200 px-2 text-center font-semibold"
                                style="position: sticky; top: 0; z-index: 20; background: #f9fafb;"
                                data-fach="{{ $fach->name }}" data-rolle="Fachlehrer"
                                data-lehrer="{{ implode(', ', $fachlehrer[$fach->id] ?? []) }}"
                                data-klassentext="{{ $klassentexte[$fach->id] ?? '' }}"
                                @if ($istAdmin || in_array($fach->id, $meineFachIds)) data-editurl="{{ route('module.schulzeugnis.klassenraeume.klassentexte.edit', ['klasse' => $klasse, 'fach' => $fach->id]) }}" @endif>{{ $fach->kuerzel ?: $fach->name }}</th>
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
                                    <form method="POST" action="{{ route('module.schulzeugnis.klassenraeume.zeugnisse.store', [$klasse, $s]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="ml-1 text-xs text-indigo-600 hover:underline">+ anlegen</button>
                                    </form>
                                @endif
                            </td>

                            {{-- Haupttext --}}
                            <td class="zt-col zt-col-haupt px-2 text-center" data-status="{{ $haupt?->status ?? '' }}">
                                @if ($haupt)
                                    @php $m = $haupt->statusMeta(); @endphp
                                    <a href="{{ route('module.schulzeugnis.klassenraeume.abschnitte.edit', $haupt) }}" title="Haupttext – {{ $m['label'] }}"
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
                                        <a href="{{ route('module.schulzeugnis.klassenraeume.abschnitte.edit', $a) }}" title="{{ $fach->name }} – {{ $m['label'] }}"
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
                                    <a href="{{ route('module.schulzeugnis.klassenraeume.zeugnisse.vorschau', $z) }}" target="_blank" title="Vorschau (HTML)" class="inline-flex text-indigo-600 hover:text-indigo-800">
                                        <i class="bx bx-show text-lg"></i>
                                    </a>
                                @else
                                    <span class="text-gray-200">–</span>
                                @endif
                            </td>
                            <td class="px-3 text-center">
                                @if ($z)
                                    <a href="{{ route('module.schulzeugnis.klassenraeume.zeugnisse.pdf', $z) }}" target="_blank" title="Als PDF herunterladen" class="inline-flex text-red-600 hover:text-red-800">
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
                                <a href="{{ route('module.schulzeugnis.schueler.jahr', $klasse->schuljahr_id) }}" class="text-indigo-600 hover:text-indigo-700">Schüler anlegen</a>.
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

        // Spaltenkopf-Tooltip: Fachlehrer + Klassentext (an body gehängt, damit der
        // Overflow der Tabelle ihn nicht abschneidet).
        (function () {
            const tip = document.createElement('div');
            tip.id = 'zt-tip';
            document.body.appendChild(tip);

            const esc = (s) => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

            let hideTimer = null;
            const cancelHide = () => { if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; } };
            const scheduleHide = () => { cancelHide(); hideTimer = setTimeout(() => tip.classList.remove('zt-show'), 220); };

            function position(th) {
                const r = th.getBoundingClientRect();
                const tw = tip.offsetWidth, thh = tip.offsetHeight;
                let left = Math.max(8, Math.min(r.left + r.width / 2 - tw / 2, window.innerWidth - tw - 8));
                let top = r.bottom + 8;
                if (top + thh > window.innerHeight - 8) top = r.top - thh - 8;
                tip.style.left = left + 'px';
                tip.style.top = top + 'px';
            }

            function show(th) {
                const rolle = th.dataset.rolle || 'Fachlehrer';
                const lehrer = (th.dataset.lehrer || '').trim();
                const ktext = (th.dataset.klassentext || '').trim();
                const editurl = th.dataset.editurl || '';
                tip.innerHTML =
                    '<div class="zt-tip-fach">' + esc(th.dataset.fach || '') + '</div>' +
                    '<div class="zt-tip-label">' + esc(rolle) + '</div>' +
                    '<div class="zt-tip-text' + (lehrer ? '' : ' zt-tip-muted') + '">' + (lehrer ? esc(lehrer) : '—') + '</div>' +
                    '<div class="zt-tip-label">Klassentext</div>' +
                    '<div class="zt-tip-text' + (ktext ? '' : ' zt-tip-muted') + '">' + (ktext ? esc(ktext) : '— kein Klassentext —') + '</div>' +
                    (editurl ? '<a class="zt-tip-link" href="' + esc(editurl) + '"><i class="bx bx-edit"></i> Klassentext bearbeiten</a>' : '');
                position(th);
                tip.classList.add('zt-show');
            }

            document.querySelectorAll('#zt-table th.zt-kopf').forEach((th) => {
                th.addEventListener('mouseenter', () => { cancelHide(); show(th); });
                th.addEventListener('mouseleave', scheduleHide);
            });
            // Über dem Tooltip bleibt er offen (damit der Link anklickbar ist).
            tip.addEventListener('mouseenter', cancelHide);
            tip.addEventListener('mouseleave', scheduleHide);
        })();
    </script>
</x-app-layout>
