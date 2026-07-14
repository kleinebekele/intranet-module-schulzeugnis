<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="category" class="text-2xl text-indigo-600" />
            <div>
                <h1 class="text-xl font-semibold text-gray-800">Vorschau – {{ $meta['titel'] }}</h1>
                <p class="text-sm text-gray-500">
                    Datei: {{ $dateiname }}@if ($schuljahr) · Ziel-Schuljahr: {{ $schuljahr->name }}@endif · Trockenlauf, es wurde noch nichts gespeichert
                </p>
            </div>
        </div>
    </x-slot>

    @php
        $zaehl = $analyse['zaehl'];
        $zuTun = $zaehl['neu'] + $zaehl['aktualisiert'];
    @endphp

    <div class="space-y-4">
        <a href="{{ route('module.schulzeugnis.import.index') }}"
           class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
            &larr; Andere Datei wählen
        </a>

        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <strong>Trockenlauf.</strong> Dies ist nur eine Vorschau – es wurde <strong>nichts geändert</strong>.
            Prüfe die Zeilen unten und klicke erst dann auf „Import jetzt ausführen".
        </div>

        @include('schulzeugnis::import._tabelle', ['analyse' => $analyse, 'nurImportierte' => true])

        <div class="rounded-xl border border-gray-200 bg-white p-6">
            @if ($zuTun > 0)
                <form method="POST" action="{{ route('module.schulzeugnis.import.ausfuehren') }}">
                    @csrf
                    <input type="hidden" name="art" value="{{ $art }}">
                    <input type="hidden" name="token" value="{{ $token }}">
                    <input type="hidden" name="dateiname" value="{{ $dateiname }}">
                    @if ($schuljahr)
                        <input type="hidden" name="schuljahr_id" value="{{ $schuljahr->id }}">
                    @endif

                    <p class="text-sm text-gray-700">
                        Es werden <strong>{{ $zaehl['neu'] }}</strong> neu angelegt und
                        <strong>{{ $zaehl['aktualisiert'] }}</strong> aktualisiert.
                        @if ($zaehl['fehler'] > 0 || $zaehl['warnung'] > 0)
                            <span class="text-amber-700">({{ $zaehl['warnung'] }} übersprungen, {{ $zaehl['fehler'] }} mit Fehler werden ignoriert.)</span>
                        @endif
                    </p>

                    <button type="submit"
                            class="mt-3 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                        Import jetzt ausführen
                    </button>
                    <a href="{{ route('module.schulzeugnis.import.index') }}"
                       class="ml-2 text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
                </form>
            @else
                <p class="text-sm text-gray-600">
                    Es gibt <strong>nichts zu importieren</strong> (nichts Neues und keine Änderungen).
                </p>
                <a href="{{ route('module.schulzeugnis.import.index') }}"
                   class="mt-3 inline-block rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Zurück
                </a>
            @endif
        </div>
    </div>
</x-app-layout>
