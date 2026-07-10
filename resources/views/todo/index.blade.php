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

        /* Kacheln der obersten Ebene in ein responsives Raster (1→2→3→4 Spalten je Breite).
           align-items: start, damit eine aufgeklappte Kachel die Nachbarn nicht mitstreckt. */
        .todo-grid { display: grid; gap: .85rem; align-items: start; grid-template-columns: 1fr; }
        @media (min-width: 720px)  { .todo-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 1100px) { .todo-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (min-width: 1700px) { .todo-grid { grid-template-columns: repeat(4, 1fr); } }
        /* Aufgeklappte Kachel dezent hervorheben. */
        .todo-node-aktiv { box-shadow: 0 8px 24px -8px rgba(79,70,229,.45); }

        /* Aufgeklappt: aktive Kachel als eigene Spalte links, restliche Kacheln rechts in
           einem Sub-Raster (Spaltenzahl = Basis − 1). So rutscht kein Element unter die
           lange, offene Kachel. Aufbau/Abbau + Gleiten passiert per JS (FLIP). */
        .todo-grid.hat-offen { display: flex; flex-wrap: wrap; gap: .85rem; align-items: flex-start; }
        .todo-grid.hat-offen > .todo-node-aktiv { flex: 1 1 100%; }
        .todo-grid.hat-offen > .todo-rest {
            flex: 1 1 100%; display: grid; gap: .85rem; grid-template-columns: 1fr; align-content: start;
        }
        @media (min-width: 720px) {
            .todo-grid.hat-offen > .todo-node-aktiv { flex: 0 0 calc(50% - .425rem); }
            .todo-grid.hat-offen > .todo-rest { flex: 1 1 0; }
        }
        @media (min-width: 1100px) {
            .todo-grid.hat-offen > .todo-node-aktiv { flex-basis: calc(33.333% - .567rem); }
            .todo-grid.hat-offen > .todo-rest { grid-template-columns: repeat(2, 1fr); }
        }
        @media (min-width: 1700px) {
            .todo-grid.hat-offen > .todo-node-aktiv { flex-basis: calc(25% - .64rem); }
            .todo-grid.hat-offen > .todo-rest { grid-template-columns: repeat(3, 1fr); }
        }

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

        .todo-zeile { transition: background .12s ease, box-shadow .2s ease; }
        .todo-zeile:hover { background: #f9fafb; }
        .todo-zeile.todo-focus { background: #fffbeb; box-shadow: inset 0 0 0 2px #f59e0b; }
        .todo-name { transition: color .12s ease; }
        .todo-zeile:hover .todo-name { color: #4f46e5; }
        .todo-verlauf { max-width: 58%; }

        /* Umschalter der Gruppierung. */
        .todo-toggle { display: inline-flex; gap: 2px; border: 1px solid #e5e7eb; border-radius: .6rem; background: #fff; padding: 3px; }
        .todo-toggle a { padding: .3rem .75rem; border-radius: .45rem; font-size: .8rem; font-weight: 500; color: #6b7280; text-decoration: none; }
        .todo-toggle a.aktiv { background: #4f46e5; color: #fff; }

        /* Tabs als abgesetzte Karten (Rahmen + Schatten), damit sie klar voneinander
           und vom Inhalt getrennt sind; der aktive Tab ist indigo hervorgehoben. */
        .todo-tabs { display: flex; flex-wrap: wrap; gap: .5rem; }
        .todo-tab-btn {
            display: inline-flex; align-items: center; gap: .45rem;
            padding: .5rem .95rem; border: 1px solid #e5e7eb; border-radius: .6rem;
            background: #fff; cursor: pointer; font-size: .875rem; font-weight: 500; color: #6b7280;
            box-shadow: 0 1px 2px rgba(0,0,0,.05);
            transition: color .15s, border-color .15s, box-shadow .15s, background .15s;
        }
        .todo-tab-btn:hover { color: #374151; border-color: #c7d2fe; }
        .todo-tab-btn.aktiv {
            color: #4f46e5; background: #eef2ff; border-color: #4f46e5;
            box-shadow: 0 3px 10px -2px rgba(79,70,229,.35);
        }
        .todo-tab-anzahl { border-radius: 9999px; background: #f3f4f6; color: #6b7280; padding: 0 .5rem; font-size: .7rem; font-weight: 600; }
        .todo-tab-btn.aktiv .todo-tab-anzahl { background: #e0e7ff; color: #4f46e5; }
        .todo-tab-gruen, .todo-tab-btn.aktiv .todo-tab-gruen { background: #dcfce7; color: #16a34a; }
        .todo-panel[hidden] { display: none; }
        .todo-erledigt-liste[hidden] { display: none; }

        /* Tab-Wechsel: kurzer Lade-Hinweis + Einblenden des neuen Panels, damit auch
           bei sehr aehnlichem Inhalt sichtbar ist, dass umgeschaltet wurde. Die Panels
           liegen bereits im DOM – der Spinner ist ein bewusster, kurzer visueller Effekt,
           kein echter Request. */
        .todo-panels { position: relative; }
        .todo-loader {
            position: absolute; inset: 0; z-index: 10;
            display: none; align-items: flex-start; justify-content: center;
            padding-top: 3.5rem;
            background: rgba(255,255,255,.65);
        }
        .todo-panels.laedt .todo-loader { display: flex; }
        .todo-spinner {
            width: 34px; height: 34px; border-radius: 9999px;
            border: 3px solid #e0e7ff; border-top-color: #4f46e5;
            animation: todo-spin .6s linear infinite;
        }
        @keyframes todo-spin { to { transform: rotate(360deg); } }
        .todo-panel.todo-einblenden { animation: todo-fade .25s ease; }
        @keyframes todo-fade { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) {
            .todo-spinner { animation-duration: 1.2s; }
            .todo-panel.todo-einblenden { animation: none; }
        }
    </style>

    <div class="space-y-6">
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
        @elseif ($meineTexteAnzahl === 0 && $erledigtAnzahl === 0 && $korrigierteAnzahl === 0 && $zuKorrigierenAnzahl === 0)
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
                        Meine Zeugnistexte
                        <span class="todo-tab-anzahl" title="offen">{{ $meineTexteAnzahl }}</span>
                        @if ($erledigtAnzahl > 0)
                            <span class="todo-tab-anzahl todo-tab-gruen" title="erledigt">{{ $erledigtAnzahl }}</span>
                        @endif
                    </button>
                    <button type="button" class="todo-tab-btn" data-tab="korrigierte">
                        Korrigierte <span class="todo-tab-anzahl" title="offen">{{ $korrigierteAnzahl }}</span>
                    </button>
                    <button type="button" class="todo-tab-btn" data-tab="zu">
                        zu Korrigieren <span class="todo-tab-anzahl" title="offen">{{ $zuKorrigierenAnzahl }}</span>
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
                    'meine'       => ['gruppen' => $meineTexteGruppen,    'leer' => 'Keine eigenen Texte.'],
                    'korrigierte' => ['gruppen' => $korrigierteGruppen,   'leer' => 'Noch nichts mit „Korrektur durchgeführt".'],
                    'zu'          => ['gruppen' => $zuKorrigierenGruppen, 'leer' => 'Aktuell nichts zu korrigieren.'],
                ];
            @endphp
            <div class="todo-panels">
                <div class="todo-loader" aria-hidden="true"><div class="todo-spinner"></div></div>
                @foreach ($panels as $key => $panel)
                    <div class="todo-panel" data-panel="{{ $key }}" hidden>
                        @if (empty($panel['gruppen']))
                            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500">
                                {{ $panel['leer'] }}
                            </div>
                        @else
                            @include('schulzeugnis::todo._gruppen', ['gruppen' => $panel['gruppen'], 'farbeKlasse' => $farbeKlasse])
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <script>
        // Tabs: gewählten Bereich anzeigen (Auswahl bleibt über den Gruppierungs-Wechsel erhalten).
        (function () {
            var tabs   = document.querySelectorAll('.todo-tab-btn');
            var panels = document.querySelectorAll('.todo-panel');
            if (!tabs.length) { return; }

            var wrap = document.querySelector('.todo-panels');

            function aktiviere(name) {
                tabs.forEach(function (t) { t.classList.toggle('aktiv', t.dataset.tab === name); });
                panels.forEach(function (p) {
                    var sichtbar = p.dataset.panel === name;
                    p.hidden = !sichtbar;
                    p.classList.remove('todo-einblenden');
                    if (sichtbar) { void p.offsetWidth; p.classList.add('todo-einblenden'); }
                });
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

            // Beim Wechsel kurz einen Spinner zeigen, dann umschalten – deutliches Signal,
            // dass sich der Inhalt geaendert hat (auch wenn zwei Panels aehnlich aussehen).
            var ladeTimer = null;
            tabs.forEach(function (t) {
                t.addEventListener('click', function () {
                    if (t.classList.contains('aktiv')) { return; }   // schon aktiv → nichts tun
                    if (!wrap) { aktiviere(t.dataset.tab); return; }
                    clearTimeout(ladeTimer);
                    wrap.classList.add('laedt');
                    ladeTimer = setTimeout(function () {
                        wrap.classList.remove('laedt');
                        aktiviere(t.dataset.tab);
                    }, 300);
                });
            });
        })();

        // „Erledigte anzeigen" ein-/ausblenden – je Gruppe (Fach bzw. Klasse) einzeln.
        document.querySelectorAll('.todo-erledigt-toggle').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var leaf = cb.closest('.todo-inhalt');
                var box  = leaf ? leaf.querySelector('.todo-erledigt-liste') : null;
                if (box) { box.hidden = !cb.checked; }
            });
        });

        // Akkordeon der zweiten Ebene: GLOBAL exklusiv. Beim Öffnen eines Fachs schließen
        // alle anderen (auch in fremden Kacheln); die betroffene Kachel wird zur eigenen
        // Spalte links, alle übrigen wandern in ein Sub-Raster rechts daneben (kein Element
        // rutscht unter die lange, offene Kachel). Umbau + FLIP-Gleiten.
        document.querySelectorAll('.todo-panel').forEach(function (panel) {
            var grid = panel.querySelector('.todo-grid');
            if (!grid) { return; }
            var akks = panel.querySelectorAll('.todo-akk');
            if (!akks.length) { return; }
            var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            // Originalreihenfolge der Kacheln – für sauberes Zurückbauen und FLIP.
            var original = [].slice.call(grid.querySelectorAll(':scope > .todo-node'));

            function messe() { return original.map(function (n) { return n.getBoundingClientRect(); }); }

            function flip(vorher) {
                if (reduce || !vorher) { return; }
                original.forEach(function (n, i) {
                    var f = vorher[i]; if (!f) { return; }
                    var l = n.getBoundingClientRect();
                    var dx = f.left - l.left, dy = f.top - l.top;
                    if (dx || dy) { n.style.transition = 'none'; n.style.transform = 'translate(' + dx + 'px,' + dy + 'px)'; }
                });
                requestAnimationFrame(function () {
                    original.forEach(function (n) { n.style.transition = 'transform .28s ease'; n.style.transform = ''; });
                });
                setTimeout(function () {
                    original.forEach(function (n) { n.style.transition = ''; n.style.transform = ''; });
                }, 340);
            }

            // Split auflösen: alle Akkordeons zu, Kacheln in Originalreihenfolge zurück ins Raster.
            function zuruecksetzen() {
                panel.querySelectorAll('.todo-akk[aria-expanded="true"]').forEach(function (o) {
                    o.setAttribute('aria-expanded', 'false');
                    var oi = o.parentElement.querySelector('.todo-inhalt'); if (oi) { oi.hidden = true; }
                });
                grid.classList.remove('hat-offen');
                original.forEach(function (n) { n.classList.remove('todo-node-aktiv'); grid.appendChild(n); });
                var rest = grid.querySelector('.todo-rest'); if (rest) { rest.remove(); }
            }

            // Split aufbauen: aktive Kachel links, alle übrigen ins Sub-Raster rechts.
            function aufbauen(node, btn, inhalt) {
                btn.setAttribute('aria-expanded', 'true');
                if (inhalt) { inhalt.hidden = false; }
                node.classList.add('todo-node-aktiv');
                grid.classList.add('hat-offen');
                grid.insertBefore(node, grid.firstChild);
                var rest = document.createElement('div');
                rest.className = 'todo-rest';
                original.forEach(function (n) { if (n !== node) { rest.appendChild(n); } });
                grid.appendChild(rest);
            }

            akks.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var offen  = btn.getAttribute('aria-expanded') === 'true';
                    var node   = btn.closest('.todo-node');
                    var inhalt = btn.parentElement.querySelector('.todo-inhalt');

                    var vorher = reduce ? null : messe();
                    zuruecksetzen();
                    if (!offen && node) { aufbauen(node, btn, inhalt); }
                    flip(vorher);

                    if (!offen && node) {
                        setTimeout(function () {
                            node.scrollIntoView({ block: 'nearest', behavior: reduce ? 'auto' : 'smooth' });
                        }, reduce ? 0 : 350);
                    }
                });
            });
        });

        // Fokus: zuletzt bearbeiteten Schüler (?focus=Abschnitt-ID) sichtbar machen –
        // richtigen Tab wählen, Gruppe (und ggf. Erledigt-Liste) aufklappen, hinscrollen.
        (function () {
            var focus = new URLSearchParams(window.location.search).get('focus');
            if (!focus) { return; }
            var row = document.querySelector('.todo-zeile[data-ab="' + CSS.escape(focus) + '"]');
            if (!row) { return; }

            // Aufklappen (defensiv – ein Fehler hier darf das Hervorheben nicht verhindern).
            try {
                var panel = row.closest('.todo-panel');
                if (panel) {
                    var tabBtn = document.querySelector('.todo-tab-btn[data-tab="' + panel.dataset.panel + '"]');
                    if (tabBtn) { tabBtn.click(); }
                }
                var inhalt = row.closest('.todo-inhalt');
                if (inhalt) {
                    var akk = inhalt.parentElement.querySelector('.todo-akk');
                    if (akk && akk.getAttribute('aria-expanded') !== 'true') { akk.click(); }
                    if (row.closest('.todo-erledigt-liste')) {
                        var cb = inhalt.querySelector('.todo-erledigt-toggle');
                        if (cb && !cb.checked) { cb.checked = true; cb.dispatchEvent(new Event('change')); }
                    }
                }
            } catch (e) { /* Aufklappen best effort */ }

            // Hervorheben + hinscrollen. Beim Laden bauen andere Skripte die Zeilen
            // einmalig neu auf (die Markierung ginge verloren), daher in der ersten
            // Sekunde per Intervall immer wieder setzen (mit erneuter Suche), danach
            // stehen lassen und nach ein paar Sekunden entfernen.
            var sel = '.todo-zeile[data-ab="' + CSS.escape(focus) + '"]';
            var gescrollt = false;
            var iv = setInterval(function () {
                var ziel = document.querySelector(sel);
                if (ziel) {
                    ziel.classList.add('todo-focus');
                    if (!gescrollt) {
                        gescrollt = true;
                        try { ziel.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (e) { ziel.scrollIntoView(); }
                    }
                }
            }, 120);
            setTimeout(function () {
                clearInterval(iv);
                var ziel = document.querySelector(sel);
                if (ziel) { ziel.classList.remove('todo-focus'); }
            }, 4500);
        })();
    </script>
</x-app-layout>
