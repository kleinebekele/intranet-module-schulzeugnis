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
        /* Klasse als Karte mit vollflächig farbiger Kopfzeile (Stufenfarbe); die
           Fächer darunter sind ein Akkordeon – je Klasse ist immer nur eines offen. */
        .todo-klasse { border: 1px solid #e5e7eb; border-radius: .75rem; overflow: hidden; }
        .todo-kopf-klasse {
            display: flex; align-items: center; gap: .625rem; padding: .7rem 1rem;
            background: linear-gradient(135deg, var(--kr), color-mix(in srgb, var(--kr) 78%, black));
        }
        .todo-kopf-weiss   { color: #fff; }
        .todo-kopf-weiss   .todo-dim   { color: rgba(255,255,255,.82); }
        .todo-kopf-weiss   .todo-badge { background: rgba(255,255,255,.22); color: #fff; }
        .todo-kopf-schwarz { color: #1f2937; }
        .todo-kopf-schwarz .todo-dim   { color: rgba(31,41,55,.7); }
        .todo-kopf-schwarz .todo-badge { background: rgba(0,0,0,.12); color: #1f2937; }
        .todo-badge { border-radius: 9999px; padding: 2px 10px; font-size: 12px; font-weight: 600; }

        .todo-faecher { background: #fff; }
        .todo-fach + .todo-fach { border-top: 1px solid #f3f4f6; }
        .todo-fach-kopf {
            width: 100%; display: flex; align-items: center; gap: .5rem;
            padding: .55rem 1rem; border: 0; text-align: left; cursor: pointer;
            background: transparent; color: #374151;
        }
        .todo-fach-kopf:hover,
        .todo-fach-kopf[aria-expanded="true"] { background: #f9fafb; }
        .todo-chevron { transition: transform .18s ease; color: #9ca3af; }
        .todo-fach-kopf[aria-expanded="true"] .todo-chevron { transform: rotate(90deg); }
        .todo-fach-badge { border-radius: 9999px; background: #f3f4f6; color: #6b7280; padding: 1px 8px; font-size: 11px; font-weight: 600; }
        .todo-fach-inhalt { padding: 0 1rem .55rem 2.1rem; }
        .todo-fach-inhalt[hidden] { display: none; }

        .todo-zeile:hover { background: #eef2ff66; }
        .todo-oeffnen { opacity: 0; transition: opacity .12s ease; }
        .todo-zeile:hover .todo-oeffnen { opacity: 1; }
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
        // Fächer-Akkordeon: innerhalb einer Klasse ist immer nur ein Fach offen.
        document.querySelectorAll('.todo-fach-kopf').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var scope  = btn.closest('.todo-faecher');
                var inhalt = btn.parentElement.querySelector('.todo-fach-inhalt');
                var offen  = btn.getAttribute('aria-expanded') === 'true';

                if (!offen && scope) {
                    scope.querySelectorAll('.todo-fach-kopf[aria-expanded="true"]').forEach(function (other) {
                        other.setAttribute('aria-expanded', 'false');
                        other.parentElement.querySelector('.todo-fach-inhalt').hidden = true;
                    });
                }

                btn.setAttribute('aria-expanded', String(!offen));
                if (inhalt) { inhalt.hidden = offen; }
            });
        });
    </script>
</x-app-layout>
