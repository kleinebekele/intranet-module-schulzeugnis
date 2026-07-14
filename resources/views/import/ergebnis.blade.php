<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="category" class="text-2xl text-indigo-600" />
            <div>
                <h1 class="text-xl font-semibold text-gray-800">Import abgeschlossen – {{ $meta['titel'] }}</h1>
                <p class="text-sm text-gray-500">Datei: {{ $dateiname }}@if ($schuljahr) · Ziel-Schuljahr: {{ $schuljahr->name }}@endif</p>
            </div>
        </div>
    </x-slot>

    @php $zaehl = $ergebnis['zaehl']; @endphp

    <div class="space-y-4">
        <div class="rounded-xl border border-green-300 bg-green-50 p-4 text-sm text-green-900">
            ✓ <strong>Fertig.</strong> {{ $zaehl['neu'] }} neu angelegt, {{ $zaehl['aktualisiert'] }} aktualisiert,
            {{ $zaehl['unveraendert'] }} unverändert.
            @if ($zaehl['warnung'] > 0 || $zaehl['fehler'] > 0)
                <span class="text-amber-800">{{ $zaehl['warnung'] }} übersprungen, {{ $zaehl['fehler'] }} mit Fehler.</span>
            @endif
        </div>

        @include('schulzeugnis::import._tabelle', ['analyse' => $ergebnis])

        <div class="flex gap-2">
            <a href="{{ route('module.schulzeugnis.import.index') }}"
               class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Nächster Import
            </a>
            <a href="{{ $weiterUrl }}"
               class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                {{ $weiterLabel }}
            </a>
        </div>
    </div>
</x-app-layout>
