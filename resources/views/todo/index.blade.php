@php
    $farbeKlasse = [
        'gray'  => 'text-gray-400',
        'amber' => 'text-amber-500',
        'red'   => 'text-red-500',
        'green' => 'text-green-600',
    ];

@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="list" class="text-2xl text-indigo-600" />
            <div>
                <h1 class="text-xl font-semibold text-gray-800">Meine ToDos</h1>
                <p class="text-sm text-gray-500">
                    @if ($schuljahr)
                        Offene Aufgaben · Schuljahr {{ $schuljahr->name }}
                    @else
                        Kein aktives Schuljahr
                    @endif
                </p>
            </div>
        </div>
    </x-slot>

    <style>
        /* Zweistufige Karte: oberste Ebene als sichtbare Kopfzeile, zweite Ebene als
           Akkordeon. Je nach Gruppierung ist die farbige Ebene die Klasse (Stufen-
           farbe, Schrift IMMER weiß) und die neutrale Ebene das Fach. */
        .todo-node { border: 1px solid #e5e7eb; border-radius: .75rem; overflow: hidden; }

        /* Farbige Elemente (Klasse) – Stufenfarbe, weiße Schrift, dunkler Badge. */
        .todo-farbe {
            color: #fff;
            background: linear-gradient(135deg,
                color-mix(in srgb, var(--kr) 85%, black),
                color-mix(in srgb, var(--kr) 60%, black));
        }
        .todo-farbe .todo-dim    { color: rgba(255,255,255,.85); }
        .todo-farbe .todo-badge  { background: rgba(0,0,0,.3); color: #fff; }
        .todo-farbe .todo-chevron { color: rgba(255,255,255,.9); }

        /* Neutrale Kopfzeile (Fach als oberste Ebene). */
        .todo-neutral-head { background: #eef2ff; color: #3730a3; }
        .todo-neutral-head .todo-badge { background: #fff; color: #4f46e5; }

        /* Kopfzeile (oberste, nicht klappbar). */
        .todo-head { display: flex; align-items: center; gap: .625rem; padding: .7rem 1rem; }

        /* Akkordeon-Kopf (zweite Ebene, klappbar). */
        .todo-kinder { background: #fff; }
        .todo-kind + .todo-kind { border-top: 1px solid #f3f4f6; }
        .todo-akk {
            width: 100%; display: flex; align-items: center; gap: .5rem;
            padding: .55rem 1rem; border: 0; text-align: left; cursor: pointer;
        }
        .todo-akk-neutral { background: transparent; color: #374151; }
        .todo-akk-neutral:hover,
        .todo-akk-neutral[aria-expanded="true"] { background: #f9fafb; }
        .todo-akk-neutral .todo-badge  { background: #f3f4f6; color: #6b7280; }
        .todo-akk-neutral .todo-chevron { color: #9ca3af; }
        .todo-akk-farbe:hover { filter: brightness(1.07); }

        .todo-chevron { transition: transform .18s ease; }
        .todo-akk[aria-expanded="true"] .todo-chevron { transform: rotate(90deg); }
        .todo-badge { border-radius: 9999px; padding: 2px 10px; font-size: 12px; font-weight: 600; white-space: nowrap; }

        .todo-inhalt { padding: .15rem 1rem .55rem 2.1rem; background: #fff; }
        .todo-inhalt[hidden] { display: none; }

        .todo-zeile { transition: background .12s ease; }
        .todo-zeile:hover { background: #f9fafb; }
        .todo-name { transition: color .12s ease; }
        .todo-zeile:hover .todo-name { color: #4f46e5; }
        .todo-verlauf { max-width: 58%; }

        /* Umschalter der Gruppierung. */
        .todo-toggle { display: inline-flex; gap: 2px; border: 1px solid #e5e7eb; border-radius: .6rem; background: #fff; padding: 3px; }
        .todo-toggle a { padding: .3rem .75rem; border-radius: .45rem; font-size: .8rem; font-weight: 500; color: #6b7280; text-decoration: none; }
        .todo-toggle a.aktiv { background: #4f46e5; color: #fff; }

        /* Tabs. */
        .todo-tabs { display: flex; gap: .25rem; border-bottom: 1px solid #e5e7eb; }
        .todo-tab-btn {
            display: inline-flex; align-items: center; gap: .45rem; margin-bottom: -1px;
            padding: .5rem .9rem; border: 0; border-bottom: 2px solid transparent;
            background: transparent; cursor: pointer; font-size: .875rem; font-weight: 500; color: #6b7280;
        }
        .todo-tab-btn:hover { color: #374151; }
        .todo-tab-btn.aktiv { color: #4f46e5; border-bottom-color: #4f46e5; }
        .todo-tab-anzahl { border-radius: 9999px; background: #f3f4f6; color: #6b7280; padding: 0 .5rem; font-size: .7rem; font-weight: 600; }
        .todo-tab-btn.aktiv .todo-tab-anzahl { background: #e0e7ff; color: #4f46e5; }
        .todo-panel[hidden] { display: none; }
    </style>

    <div class="max-w-4xl space-y-6">
        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ session('error') }}</div>
        @endif

        @if ($istAdmin)
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-gray-600">
                <div class="flex items-start gap-3">
                    <i class="bx bx-info-circle mt-0.5 text-xl text-indigo-500"></i>
                    <div>
                        <p class="font-medium text-gray-800">Als Administrator hast du hier keine ToDos.</p>
                        <p class="mt-1 text-sm text-gray-500">Du hast nur Einsicht in die Zeugnisse. ToDos erscheinen nur bei den zuständigen Lehrkräften.</p>
                    </div>
                </div>
            </div>
        @elseif (! $schuljahr)
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                Es ist kein Schuljahr aktiv geschaltet.
            </div>
        @elseif ($meineTexteAnzahl === 0 && $korrigierteAnzahl === 0 && $zuKorrigierenAnzahl === 0)
            <div class="rounded-xl border border-gray-200 bg-white p-8 text-center">
                <i class="bx bx-check-circle text-3xl text-green-500"></i>
                <p class="mt-2 font-medium text-gray-800">Alles erledigt.</p>
                <p class="text-sm text-gray-500">Für dich sind aktuell keine offenen ToDos hinterlegt.</p>
            </div>
        @else
            {{-- Tabs + Gruppierungs-Umschalter --}}
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="todo-tabs">
                    <button type="button" class="todo-tab-btn" data-tab="meine">
                        Meine Zeugnistexte <span class="todo-tab-anzahl">{{ $meineTexteAnzahl }}</span>
                    </button>
                    <button type="button" class="todo-tab-btn" data-tab="korrigierte">
                        Korrigierte <span class="todo-tab-anzahl">{{ $korrigierteAnzahl }}</span>
                    </button>
                    <button type="button" class="todo-tab-btn" data-tab="zu">
                        zu Korrigieren <span class="todo-tab-anzahl">{{ $zuKorrigierenAnzahl }}</span>
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Gruppieren nach</span>
                    <div class="todo-toggle">
                        <a href="{{ route('module.schulzeugnis.todo.index', ['gruppierung' => 'klasse']) }}" data-gruppierung="klasse" class="{{ $modus === 'klasse' ? 'aktiv' : '' }}">Klasse › Fach</a>
                        <a href="{{ route('module.schulzeugnis.todo.index', ['gruppierung' => 'fach']) }}" data-gruppierung="fach" class="{{ $modus === 'fach' ? 'aktiv' : '' }}">Fach › Klasse</a>
                    </div>
                </div>
            </div>

            @php
                $panels = [
                    'meine'       => ['gruppen' => $meineTexteGruppen,    'leer' => 'Keine offenen eigenen Texte.'],
                    'korrigierte' => ['gruppen' => $korrigierteGruppen,   'leer' => 'Noch nichts mit „Korrektur durchgeführt".'],
                    'zu'          => ['gruppen' => $zuKorrigierenGruppen, 'leer' => 'Aktuell nichts zu korrigieren.'],
                ];
            @endphp
            @foreach ($panels as $key => $panel)
                <div class="todo-panel" data-panel="{{ $key }}" hidden>
                    @if (empty($panel['gruppen']))
                        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500">
                            {{ $panel['leer'] }}
                        </div>
                    @else
                        @include('schulzeugnis::todo._gruppen', ['gruppen' => $panel['gruppen'], 'farbeKlasse' => $farbeKlasse, 'letzteAenderung' => $letzteAenderung])
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    <script>
        // Tabs: gewählten Bereich anzeigen (Auswahl bleibt über den Gruppierungs-Wechsel erhalten).
        (function () {
            var tabs   = document.querySelectorAll('.todo-tab-btn');
            var panels = document.querySelectorAll('.todo-panel');
            if (!tabs.length) { return; }

            function aktiviere(name) {
                tabs.forEach(function (t) { t.classList.toggle('aktiv', t.dataset.tab === name); });
                panels.forEach(function (p) { p.hidden = p.dataset.panel !== name; });
                // Gruppierungs-Links den aktuellen Tab mitgeben, damit er nach dem Reload bleibt.
                document.querySelectorAll('.todo-toggle a').forEach(function (a) {
                    var url = new URL(a.href, window.location.origin);
                    url.searchParams.set('tab', name);
                    a.href = url.pathname + url.search;
                });
            }

            var gewuenscht = new URLSearchParams(window.location.search).get('tab');
            var start = Array.prototype.some.call(tabs, function (t) { return t.dataset.tab === gewuenscht; }) ? gewuenscht : 'meine';
            aktiviere(start);

            tabs.forEach(function (t) {
                t.addEventListener('click', function () { aktiviere(t.dataset.tab); });
            });
        })();

        // Akkordeon der zweiten Ebene: pro Kopfzeile ist immer nur ein Eintrag offen.
        document.querySelectorAll('.todo-akk').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var scope  = btn.closest('.todo-kinder');
                var inhalt = btn.parentElement.querySelector('.todo-inhalt');
                var offen  = btn.getAttribute('aria-expanded') === 'true';

                if (!offen && scope) {
                    scope.querySelectorAll('.todo-akk[aria-expanded="true"]').forEach(function (other) {
                        other.setAttribute('aria-expanded', 'false');
                        other.parentElement.querySelector('.todo-inhalt').hidden = true;
                    });
                }

                btn.setAttribute('aria-expanded', String(!offen));
                if (inhalt) { inhalt.hidden = offen; }
            });
        });
    </script>
</x-app-layout>
