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

    <div class="max-w-3xl space-y-6">
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
                    <div class="space-y-3">
                        @include('schulzeugnis::todo._gruppen', ['gruppen' => $eigeneGruppen, 'farbeKlasse' => $farbeKlasse])
                    </div>
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
                    <div class="space-y-3">
                        @include('schulzeugnis::todo._gruppen', ['gruppen' => $korrekturGruppen, 'farbeKlasse' => $farbeKlasse])
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-app-layout>
