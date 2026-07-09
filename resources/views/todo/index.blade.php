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

        .todo-zeile:hover { background: #eef2ff66; }
        .todo-oeffnen { opacity: 0; transition: opacity .12s ease; }
        .todo-zeile:hover .todo-oeffnen { opacity: 1; }

        /* Umschalter der Gruppierung. */
        .todo-toggle { display: inline-flex; gap: 2px; border: 1px solid #e5e7eb; border-radius: .6rem; background: #fff; padding: 3px; }
        .todo-toggle a { padding: .3rem .75rem; border-radius: .45rem; font-size: .8rem; font-weight: 500; color: #6b7280; text-decoration: none; }
        .todo-toggle a.aktiv { background: #4f46e5; color: #fff; }
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
        @elseif ($eigeneAnzahl === 0 && $korrekturAnzahl === 0)
            <div class="rounded-xl border border-gray-200 bg-white p-8 text-center">
                <i class="bx bx-check-circle text-3xl text-green-500"></i>
                <p class="mt-2 font-medium text-gray-800">Alles erledigt.</p>
                <p class="text-sm text-gray-500">Für dich sind aktuell keine offenen ToDos hinterlegt.</p>
            </div>
        @else
            {{-- Umschalter der Gruppierungsrichtung --}}
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Gruppieren nach</span>
                <div class="todo-toggle">
                    <a href="{{ route('module.schulzeugnis.todo.index', ['gruppierung' => 'klasse']) }}" class="{{ $modus === 'klasse' ? 'aktiv' : '' }}">Klasse › Fach</a>
                    <a href="{{ route('module.schulzeugnis.todo.index', ['gruppierung' => 'fach']) }}" class="{{ $modus === 'fach' ? 'aktiv' : '' }}">Fach › Klasse</a>
                </div>
            </div>

            {{-- Bereich 1: Eigene Zeugnistexte --}}
            <section>
                <div class="mb-2 flex items-center gap-2">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Meine Zeugnistexte</h2>
                    <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700">{{ $eigeneAnzahl }}</span>
                </div>

                @if (empty($eigeneGruppen))
                    <div class="rounded-xl border border-dashed border-gray-300 bg-white p-5 text-center text-sm text-gray-500">
                        Keine offenen eigenen Texte – alles vollständig.
                    </div>
                @else
                    @include('schulzeugnis::todo._gruppen', ['gruppen' => $eigeneGruppen, 'farbeKlasse' => $farbeKlasse, 'letzteAenderung' => $letzteAenderung])
                @endif
            </section>

            {{-- Bereich 2: Korrektur-Anfragen --}}
            <section>
                <div class="mb-2 flex items-center gap-2">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Um Korrektur gebeten</h2>
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">{{ $korrekturAnzahl }}</span>
                </div>

                @if (empty($korrekturGruppen))
                    <div class="rounded-xl border border-dashed border-gray-300 bg-white p-5 text-center text-sm text-gray-500">
                        Aktuell keine offenen Korrektur-Anfragen an dich.
                    </div>
                @else
                    @include('schulzeugnis::todo._gruppen', ['gruppen' => $korrekturGruppen, 'farbeKlasse' => $farbeKlasse, 'letzteAenderung' => $letzteAenderung])
                @endif
            </section>
        @endif
    </div>

    <script>
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
