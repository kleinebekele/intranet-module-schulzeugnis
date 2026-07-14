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
        /* Fach-Spalten + die drei Aktionsspalten (Warnung/Vorschau/PDF) schmal halten –
           sie zeigen nur ein Icon bzw. ein kurzes Kuerzel. */
        #zt-table .zt-col, #zt-table .zt-mini { width: 35px; max-width: 35px; }
        #zt-table th.zt-col, #zt-table th.zt-mini { padding-left: 3px; padding-right: 3px; }
        #zt-table td.zt-col, #zt-table td.zt-mini { padding-left: 2px; padding-right: 2px; }
        #zt-table tr.zt-focus td { background: #fffbeb !important; transition: background .3s ease; }
        #zt-table td.zt-focus-cell { box-shadow: inset 0 0 0 2px #f59e0b; border-radius: 4px; }

        /* Zebra + deutliche Hover-Zeile, damit man in der breiten Matrix nicht in der
           Zeile verrutscht. Gilt auch fuer die fixierte Schueler-Spalte – deren
           Hintergrund muss opak bleiben, sonst scheint beim Querscrollen der Inhalt durch. */
        #zt-table tbody tr:nth-child(even) td { background: #f6f7fb; }
        #zt-table tbody tr:nth-child(odd)  td { background: #ffffff; }
        #zt-table tbody tr:hover td { background: #e0e7ff !important; }
        #zt-table tbody tr:hover td:first-child { box-shadow: inset 3px 0 0 #4f46e5; }
        #zt-table tbody tr:hover td:first-child .font-medium { color: #3730a3; }
        .zt-chip { border: 1px solid #d1d5db; border-radius: 9999px; padding: 2px 10px; font-size: 12px; color: #374151; background: #fff; cursor: pointer; }
        .zt-chip:hover { background: #f3f4f6; }
        .zt-chip.zt-off { opacity: .45; text-decoration: line-through; }
        .zt-chip.zt-preset { border-color: #6366f1; color: #4f46e5; background: #eef2ff; font-weight: 600; }
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

        @if ($istAdmin && ! $gsVerfuegbar)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <div class="flex items-start gap-2">
                    <i class="bx bx-error mt-0.5 text-lg text-amber-600"></i>
                    <div>
                        <p class="font-semibold">Ghostscript nicht installiert – keine PDF/A-Langzeitarchivierung möglich</p>
                        <p class="mt-1">Zeugnis-PDFs werden als <strong>normale PDFs</strong> (PDF 1.7) erzeugt, nicht im revisionssicheren Archivformat <strong>PDF/A-2b</strong> (eingebettete Fonts, definierter Farbraum, in sich geschlossen). Für die dauerhafte Archivierung fehlt damit die Konvertierung – Vorschau, Druck und normaler Download funktionieren uneingeschränkt.</p>
                        <p class="mt-2 text-xs">
                            Installation auf dem Server:
                            <code class="rounded bg-amber-100 px-1 py-0.5">sudo apt install ghostscript</code>
                            &middot; danach prüfen mit
                            <code class="rounded bg-amber-100 px-1 py-0.5">gs --version</code>.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ session('error') }}</div>
        @endif

        {{-- Filterleiste --}}
        <div class="space-y-2 rounded-xl border border-gray-200 bg-white p-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Spalten</span>
                <button type="button" id="zt-mine" class="zt-chip">Meine Fächer</button>
                <button type="button" id="zt-all" class="zt-chip">Alle</button>
                <span class="mx-1 text-gray-300">|</span>
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

        <div class="rounded-xl border border-gray-200 bg-white" style="max-height: 74vh; overflow: auto; width: fit-content; max-width: 100%;">
            <table id="zt-table" class="border-collapse text-sm">
                <thead>
                    <tr class="text-gray-600">
                        <th class="border-b border-r border-gray-200 px-4 text-left font-semibold" style="position: sticky; left: 0; top: 0; z-index: 30; background: #f9fafb;">Schüler</th>
                        @if ($hatHaupt)
                            <th class="zt-kopf border-b border-gray-200 px-2 text-center font-semibold"
                                style="position: sticky; top: 0; z-index: 20; background: #eef2ff;"
                                data-fach="Hauptzeugnis" data-rolle="Klassenlehrer"
                                data-lehrer="{{ $klasse->klassenlehrer?->fullName() }}"
                                data-klassentext="{{ $klassentexte['haupt'] ?? '' }}"
                                title="Hauptzeugnis">HAU</th>
                            <th class="zt-mini border-b border-gray-200 text-center font-semibold" style="position: sticky; top: 0; z-index: 20; background: #eef2ff;" title="Warnhinweis Hauptzeugnis">⚠</th>
                            <th class="zt-mini border-b border-gray-200 text-center font-semibold" style="position: sticky; top: 0; z-index: 20; background: #eef2ff;" title="Vorschau Hauptzeugnis"><i class="bx bx-show"></i></th>
                            <th class="zt-mini border-b border-r border-gray-200 text-center font-semibold" style="position: sticky; top: 0; z-index: 20; background: #eef2ff;" title="PDF Hauptzeugnis">PDF</th>
                        @endif
                        @foreach ($faecher as $fach)
                            <th class="zt-col zt-col-{{ $fach->id }} zt-kopf border-b border-gray-200 px-2 text-center font-semibold"
                                style="position: sticky; top: 0; z-index: 20; background: #f9fafb;"
                                data-fach="{{ $fach->name }}" data-rolle="Fachlehrer"
                                data-lehrer="{{ implode(', ', $fachlehrer[$fach->id] ?? []) }}"
                                data-klassentext="{{ $klassentexte[$fach->id] ?? '' }}"
                                @if ($istAdmin || in_array($fach->id, $meineFachIds) || in_array($fach->id, $ktKorrektorKeys)) data-editurl="{{ route('module.schulzeugnis.klassenraeume.klassentexte.edit', ['klasse' => $klasse, 'fach' => $fach->id]) }}" @endif>{{ $fach->kuerzel ?: $fach->name }}</th>
                        @endforeach
                        @if ($hatSpruch)
                            <th class="zt-kopf border-b border-l border-gray-200 px-2 text-center font-semibold"
                                style="position: sticky; top: 0; z-index: 20; background: #f9fafb;"
                                data-fach="Zeugnisspruch" data-rolle="Klassenlehrer"
                                data-lehrer="{{ $klasse->klassenlehrer?->fullName() }}"
                                title="Zeugnisspruch">ZEU</th>
                        @endif
                        <th class="zt-mini border-b border-l border-gray-200 text-center font-semibold" style="position: sticky; top: 0; z-index: 20; background: #f9fafb;" title="Warnhinweis Textlänge">⚠</th>
                        <th class="zt-mini border-b border-gray-200 text-center font-semibold" style="position: sticky; top: 0; z-index: 20; background: #f9fafb;" title="Vorschau (HTML)"><i class="bx bx-show"></i></th>
                        <th class="zt-mini border-b border-gray-200 text-center font-semibold" style="position: sticky; top: 0; z-index: 20; background: #f9fafb;">PDF</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Klassenweit-Zeile: gemeinsame (Klassen-)Texte je Spalte, mit eigenem Status. --}}
                    <tr class="zt-klassenweit border-b border-gray-200">
                        <td class="border-r border-gray-200 px-4 whitespace-nowrap font-semibold text-indigo-800"
                            style="position: sticky; left: 0; z-index: 10; background: #eef2ff;">Klassenweit</td>
                        @if ($hatHaupt)
                            @php $ktH = $ktRows['haupt'] ?? null; $kmH = $ktH?->statusMeta() ?? $stati['unbearbeitet']; @endphp
                            <td class="px-2 text-center" style="background: #eef2ff;">
                                @if ($istAdmin || $binKlassenlehrer || in_array('haupt', $ktKorrektorKeys))
                                    <a href="{{ route('module.schulzeugnis.klassenraeume.klassentexte.edit', ['klasse' => $klasse, 'fach' => 'haupt']) }}"
                                       title="Klassentext Hauptzeugnis – {{ $kmH['label'] }}" class="inline-flex rounded p-0.5 hover:bg-indigo-100">
                                        <i class="bx {{ $kmH['icon'] }} text-lg {{ $farbeKlasse[$kmH['farbe']] ?? 'text-gray-300' }}"></i>
                                    </a>
                                @else
                                    <i class="bx {{ $kmH['icon'] }} text-lg {{ $farbeKlasse[$kmH['farbe']] ?? 'text-gray-300' }}" title="Klassentext Hauptzeugnis – {{ $kmH['label'] }}"></i>
                                @endif
                            </td>
                            <td class="zt-mini text-center" style="background: #eef2ff;">
                                @if ($warnAgg['haupt'])
                                    <i class="bx bxs-error text-amber-600" title="Bei mindestens einem Schüler ist der Hauptzeugnis-Text zu lang"></i>
                                @else
                                    <span class="text-gray-300">–</span>
                                @endif
                            </td>
                            <td class="zt-mini text-center" style="background: #eef2ff;">
                                <a href="{{ route('module.schulzeugnis.klassenraeume.sammel.vorschau', ['klasse' => $klasse, 'typ' => 'haupt']) }}" target="_blank" title="Vorschau ALLER Hauptzeugnisse" class="inline-flex text-indigo-600 hover:text-indigo-800"><i class="bx bx-show text-lg"></i></a>
                            </td>
                            <td class="zt-mini border-r border-gray-200 text-center" style="background: #eef2ff;">
                                <a href="{{ route('module.schulzeugnis.klassenraeume.sammel.pdf', ['klasse' => $klasse, 'typ' => 'haupt']) }}" target="_blank" title="Alle Hauptzeugnisse als EINE PDF" class="inline-flex text-red-600 hover:text-red-800"><i class="bx bxs-file-pdf text-lg"></i></a>
                            </td>
                        @endif
                        @foreach ($faecher as $fach)
                            @php $ktF = $ktRows[$fach->id] ?? null; $kmF = $ktF?->statusMeta() ?? $stati['unbearbeitet']; @endphp
                            <td class="zt-col zt-col-{{ $fach->id }} px-2 text-center" style="background: #eef2ff;">
                                @if ($istAdmin || in_array($fach->id, $meineFachIds) || in_array($fach->id, $ktKorrektorKeys))
                                    <a href="{{ route('module.schulzeugnis.klassenraeume.klassentexte.edit', ['klasse' => $klasse, 'fach' => $fach->id]) }}"
                                       title="Klassentext {{ $fach->name }} – {{ $kmF['label'] }}" class="inline-flex rounded p-0.5 hover:bg-indigo-100">
                                        <i class="bx {{ $kmF['icon'] }} text-lg {{ $farbeKlasse[$kmF['farbe']] ?? 'text-gray-300' }}"></i>
                                    </a>
                                @else
                                    <i class="bx {{ $kmF['icon'] }} text-lg {{ $farbeKlasse[$kmF['farbe']] ?? 'text-gray-300' }}" title="Klassentext {{ $fach->name }} – {{ $kmF['label'] }}"></i>
                                @endif
                            </td>
                        @endforeach
                        @if ($hatSpruch)
                            @php $ktS = $ktRows['spruch'] ?? null; $kmS = $ktS?->statusMeta() ?? $stati['unbearbeitet']; @endphp
                            <td class="border-l border-gray-200 px-2 text-center" style="background: #eef2ff;">
                                @if ($istAdmin || $binKlassenlehrer || in_array('spruch', $ktKorrektorKeys))
                                    <a href="{{ route('module.schulzeugnis.klassenraeume.klassentexte.edit', ['klasse' => $klasse, 'fach' => 'spruch']) }}"
                                       title="Klassenweiter Zeugnisspruch – {{ $kmS['label'] }}" class="inline-flex rounded p-0.5 hover:bg-indigo-100">
                                        <i class="bx {{ $kmS['icon'] }} text-lg {{ $farbeKlasse[$kmS['farbe']] ?? 'text-gray-300' }}"></i>
                                    </a>
                                @else
                                    <i class="bx {{ $kmS['icon'] }} text-lg {{ $farbeKlasse[$kmS['farbe']] ?? 'text-gray-300' }}" title="Klassenweiter Zeugnisspruch – {{ $kmS['label'] }}"></i>
                                @endif
                            </td>
                        @endif
                        <td class="zt-mini border-l border-gray-200 text-center" style="background: #eef2ff;">
                            @if ($warnAgg['fach'])
                                <i class="bx bxs-error text-amber-600" title="Bei mindestens einem Schüler ist der Fachzeugnis-Text zu lang"></i>
                            @else
                                <span class="text-gray-300">–</span>
                            @endif
                        </td>
                        <td class="zt-mini text-center" style="background: #eef2ff;">
                            <a href="{{ route('module.schulzeugnis.klassenraeume.sammel.vorschau', ['klasse' => $klasse, 'typ' => 'fach']) }}" target="_blank" title="Vorschau ALLER Fachzeugnisse" class="inline-flex text-indigo-600 hover:text-indigo-800"><i class="bx bx-show text-lg"></i></a>
                        </td>
                        <td class="zt-mini text-center" style="background: #eef2ff;">
                            <a href="{{ route('module.schulzeugnis.klassenraeume.sammel.pdf', ['klasse' => $klasse, 'typ' => 'fach']) }}" target="_blank" title="Alle Fachzeugnisse als EINE PDF" class="inline-flex text-red-600 hover:text-red-800"><i class="bx bxs-file-pdf text-lg"></i></a>
                        </td>
                    </tr>

                    @forelse ($schueler as $s)
                        @php
                            $z = $s->fachzeugnis;
                            $hz = $s->hauptzeugnis;
                            $abs = $z ? $z->abschnitte : collect();
                            $fachMap = $abs->whereIn('typ', ['fachtext', 'note'])->keyBy('fach_id');
                            $haz = $hz ? $hz->abschnitte->firstWhere('typ', 'hauptzeugnis') : null;
                        @endphp
                        <tr class="border-b border-gray-100">
                            <td class="border-r border-gray-200 px-4 whitespace-nowrap" style="position: sticky; left: 0; z-index: 10;">
                                <span class="font-medium text-gray-800">{{ $s->nachname }}, {{ $s->vorname }}</span>
                            </td>

                            @if ($hatHaupt)
                                @php $wh = $warnungen[$s->id]['haupt'] ?? null; @endphp
                                {{-- Hauptzeugnis (HAU) + Warnung/Vorschau/PDF --}}
                                <td class="px-2 text-center" data-status="{{ $haz?->status ?? '' }}">
                                    @if ($haz)
                                        @php $hm = $haz->statusMeta(); @endphp
                                        <a href="{{ route('module.schulzeugnis.klassenraeume.abschnitte.edit', $haz) }}" title="Hauptzeugnis – {{ $hm['label'] }}"
                                           data-ab="{{ $haz->id }}"
                                           class="inline-flex rounded p-0.5 hover:bg-indigo-100">
                                            <i class="bx {{ $hm['icon'] }} text-lg {{ $farbeKlasse[$hm['farbe']] ?? 'text-gray-300' }}"></i>
                                        </a>
                                    @else
                                        <span class="text-gray-200">–</span>
                                    @endif
                                </td>
                                <td class="zt-mini text-center">
                                    @if ($wh && $wh['status'] === 'verkleinert')
                                        <i class="bx bx-error-circle text-amber-600" title="Text zu lang – passt verkleinert bei {{ $wh['passtBei'] }} pt"></i>
                                    @elseif ($wh && $wh['status'] === 'ueberlauf')
                                        <i class="bx bxs-error text-red-600" title="Text passt auch bei kleinster Schrift nicht vollständig"></i>
                                    @elseif ($wh && $wh['status'] === 'ok')
                                        <i class="bx bx-check text-green-500" title="passt"></i>
                                    @else
                                        <span class="text-gray-200">–</span>
                                    @endif
                                </td>
                                <td class="zt-mini text-center">
                                    @if ($hz)
                                        <a href="{{ route('module.schulzeugnis.klassenraeume.zeugnisse.vorschau', $hz) }}" target="_blank" title="Vorschau Hauptzeugnis" class="inline-flex text-indigo-600 hover:text-indigo-800"><i class="bx bx-show text-lg"></i></a>
                                    @else
                                        <span class="text-gray-200">–</span>
                                    @endif
                                </td>
                                <td class="zt-mini border-r border-gray-200 text-center">
                                    @if ($hz)
                                        <a href="{{ route('module.schulzeugnis.klassenraeume.zeugnisse.pdf', $hz) }}" target="_blank" title="Hauptzeugnis als PDF" class="inline-flex text-red-600 hover:text-red-800"><i class="bx bxs-file-pdf text-lg"></i></a>
                                    @else
                                        <span class="text-gray-200">–</span>
                                    @endif
                                </td>
                            @endif

                            {{-- Fächer --}}
                            @foreach ($faecher as $fach)
                                @php $a = $fachMap->get($fach->id); @endphp
                                <td class="zt-col zt-col-{{ $fach->id }} px-2 text-center" data-status="{{ $a?->status ?? '' }}">
                                    @if ($a)
                                        @php $m = $a->statusMeta(); @endphp
                                        <a href="{{ route('module.schulzeugnis.klassenraeume.abschnitte.edit', $a) }}" title="{{ $fach->name }} – {{ $m['label'] }}"
                                           data-ab="{{ $a->id }}"
                                           class="inline-flex rounded p-0.5 hover:bg-indigo-100">
                                            <i class="bx {{ $m['icon'] }} text-lg {{ $farbeKlasse[$m['farbe']] ?? 'text-gray-300' }}"></i>
                                        </a>
                                    @else
                                        <span class="text-gray-200">–</span>
                                    @endif
                                </td>
                            @endforeach

                            {{-- Zeugnisspruch (Schüler) --}}
                            @if ($hatSpruch)
                                @php $sp = $spruchAbschnitte[$s->id] ?? null; @endphp
                                <td class="border-l border-gray-200 px-2 text-center" data-status="{{ $sp?->status ?? '' }}">
                                    @if ($sp)
                                        @php $sm = $sp->statusMeta(); @endphp
                                        <a href="{{ route('module.schulzeugnis.klassenraeume.abschnitte.edit', $sp) }}" title="Zeugnisspruch – {{ $sm['label'] }}"
                                           data-ab="{{ $sp->id }}" class="inline-flex rounded p-0.5 hover:bg-indigo-100">
                                            <i class="bx {{ $sm['icon'] }} text-lg {{ $farbeKlasse[$sm['farbe']] ?? 'text-gray-300' }}"></i>
                                        </a>
                                    @else
                                        <span class="text-gray-200">–</span>
                                    @endif
                                </td>
                            @endif

                            {{-- Warnhinweis Textlänge (Fachzeugnis) --}}
                            @php $w = $warnungen[$s->id]['fach'] ?? null; @endphp
                            <td class="zt-mini border-l border-gray-200 text-center">
                                @if ($w && $w['status'] === 'verkleinert')
                                    <i class="bx bx-error-circle text-amber-600" title="Text zu lang – passt automatisch verkleinert bei {{ $w['passtBei'] }} pt"></i>
                                @elseif ($w && $w['status'] === 'ueberlauf')
                                    <i class="bx bxs-error text-red-600" title="Text passt auch bei kleinster Schrift nicht vollständig"></i>
                                @elseif ($w && $w['status'] === 'ok')
                                    <i class="bx bx-check text-green-500" title="passt"></i>
                                @else
                                    <span class="text-gray-200">–</span>
                                @endif
                            </td>

                            {{-- Vorschau / PDF --}}
                            <td class="zt-mini text-center">
                                @if ($z)
                                    <a href="{{ route('module.schulzeugnis.klassenraeume.zeugnisse.vorschau', $z) }}" target="_blank" title="Vorschau (HTML)" class="inline-flex text-indigo-600 hover:text-indigo-800">
                                        <i class="bx bx-show text-lg"></i>
                                    </a>
                                @else
                                    <span class="text-gray-200">–</span>
                                @endif
                            </td>
                            <td class="zt-mini text-center">
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
                            <td colspan="{{ $faecher->count() + ($hatHaupt ? 4 : 0) + 4 }}" class="px-4 py-8 text-center text-gray-500">
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

            function setCol(key, show) {
                document.querySelectorAll('.zt-col-' + key).forEach((el) => el.classList.toggle('zt-hidden-col', !show));
                const chip = document.querySelector('.zt-chip[data-col="' + key + '"]');
                if (chip) chip.classList.toggle('zt-off', !show);
            }

            function applyRows() {
                const st = statusSel.value;
                let sichtbar = 0;
                table.querySelectorAll('tbody tr:not(.zt-klassenweit)').forEach((tr) => {
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

            // "Alle" / "Meine Fächer" nur hervorheben, wenn die sichtbaren Spalten GENAU
            // dieser Auswahl entsprechen – sonst ist keiner der beiden markiert.
            function updatePresets() {
                const chips = [...document.querySelectorAll('.zt-chip[data-col]')];
                const sichtbar = (key) => {
                    const c = document.querySelector('.zt-chip[data-col="' + key + '"]');
                    return c ? !c.classList.contains('zt-off') : false;
                };
                const alleAn = chips.every((c) => !c.classList.contains('zt-off'));
                let meineAn = true;
                if (meineAn) {
                    for (const c of chips) {
                        const key = c.dataset.col;
                        if (key === 'haupt') { continue; }
                        if (mineIds.includes(key) !== sichtbar(key)) { meineAn = false; break; }
                    }
                }
                document.getElementById('zt-all').classList.toggle('zt-preset', alleAn);
                document.getElementById('zt-mine').classList.toggle('zt-preset', meineAn && !alleAn);
            }

            document.querySelectorAll('.zt-chip[data-col]').forEach((chip) => {
                chip.addEventListener('click', () => { setCol(chip.dataset.col, chip.classList.contains('zt-off')); applyRows(); updatePresets(); });
            });
            document.getElementById('zt-mine').addEventListener('click', () => {
                @foreach ($faecher as $fach)
                    setCol('{{ $fach->id }}', mineIds.includes('{{ $fach->id }}'));
                @endforeach
                applyRows(); updatePresets();
            });
            document.getElementById('zt-all').addEventListener('click', () => {
                @foreach ($faecher as $fach)
                    setCol('{{ $fach->id }}', true);
                @endforeach
                applyRows(); updatePresets();
            });
            statusSel.addEventListener('change', applyRows);

            // Für Lehrer: standardmäßig auf die eigenen Fächer filtern; Admins sehen alles.
            if (mineIds.length) {
                document.getElementById('zt-mine').click();
            }
            updatePresets();

            // Fokus: zuletzt bearbeiteten Abschnitt (?focus=ID) sichtbar machen –
            // ggf. Spalte einblenden, Status-Filter lösen, hinscrollen, hervorheben.
            (function () {
                const focus = new URLSearchParams(window.location.search).get('focus');
                if (!focus) { return; }
                const linkSel = 'a[data-ab="' + CSS.escape(focus) + '"]';
                const link = table.querySelector(linkSel);
                if (!link) { return; }

                const cell0 = link.closest('td');
                if (cell0 && cell0.classList.contains('zt-hidden-col')) {
                    const colClass = [...cell0.classList].find((c) => c.startsWith('zt-col-') && c !== 'zt-col');
                    if (colClass) { setCol(colClass.replace('zt-col-', ''), true); }
                }
                if (link.closest('tr') && link.closest('tr').style.display === 'none') { statusSel.value = ''; applyRows(); }

                // Markierung in der ersten Sekunde wiederholt setzen (überlebt ein
                // einmaliges Neu-Rendern der Zeilen beim Laden). Sie bleibt danach
                // bestehen, bis man die Seite verlässt – nicht mehr automatisch entfernen.
                let gescrollt = false;
                const iv = setInterval(function () {
                    const l = table.querySelector(linkSel);
                    if (l) {
                        const tr = l.closest('tr'), td = l.closest('td');
                        if (tr) { tr.classList.add('zt-focus'); }
                        if (td) { td.classList.add('zt-focus-cell'); }
                        if (!gescrollt && tr) { gescrollt = true; tr.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
                    }
                }, 120);
                setTimeout(function () { clearInterval(iv); }, 1500);
            })();
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
