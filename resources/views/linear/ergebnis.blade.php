<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <i class="bx bx-import text-2xl text-indigo-600"></i>
            <div>
                <h1 class="text-xl font-semibold text-gray-800">Aus Linear importiert – {{ $meta['titel'] }}</h1>
                <p class="text-sm text-gray-500">Schuljahr {{ $schuljahr->name }}</p>
            </div>
        </div>
    </x-slot>

    @php $zaehl = $ergebnis['zaehl']; @endphp

    <div class="space-y-4">
        <div class="rounded-xl border border-green-300 bg-green-50 p-4 text-sm text-green-900">
            ✓ <strong>Fertig.</strong> {{ $zaehl['neu'] }} neu angelegt, {{ $zaehl['aktualisiert'] }} aktualisiert,
            {{ $zaehl['unveraendert'] }} unverändert{{ $nurNeu ? ' (Änderungen bewusst nicht übernommen)' : '' }}.
            @if ($zaehl['warnung'] > 0 || $zaehl['fehler'] > 0)
                <span class="text-amber-800">{{ $zaehl['warnung'] }} übersprungen, {{ $zaehl['fehler'] }} mit Fehler.</span>
            @endif
        </div>

        @include('schulzeugnis::import._tabelle', ['analyse' => $ergebnis])

        <a href="{{ $zurueckUrl }}"
           class="inline-block rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            Zurück zu {{ $meta['titel'] }}
        </a>
    </div>
</x-app-layout>
