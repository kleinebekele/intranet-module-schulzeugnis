<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <i class="bx bx-import text-2xl text-indigo-600"></i>
            <div>
                <h1 class="text-xl font-semibold text-gray-800">Aus Linear – {{ $meta['titel'] }}</h1>
                <p class="text-sm text-gray-500">Schuljahr {{ $schuljahr->name }}</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-4">
        <a href="{{ $zurueckUrl }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
            &larr; Zurück zu {{ $meta['titel'] }}
        </a>

        <form method="GET" action="{{ $formUrl }}" class="space-y-4 rounded-xl border border-gray-200 bg-white p-6">
            @if ($art === 'faecher')
                <input type="hidden" name="schuljahr" value="{{ $schuljahr->id }}">
            @endif
            <p class="text-sm text-gray-700">
                Das Schuljahr <strong>{{ $schuljahr->name }}</strong> ist noch nicht mit Linear verknüpft, und kein
                Linear-Zeitraum trägt denselben Namen. Welcher Zeitraum gehört dazu?
            </p>

            @if (empty($zeitraeume))
                <p class="text-sm text-amber-700">Linear meldet keinen Zeitraum mit Schülern.</p>
            @else
                <div>
                    <label for="zeitraum" class="block text-sm font-medium text-gray-700">Linear-Zeitraum</label>
                    <select name="zeitraum" id="zeitraum"
                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($zeitraeume as $z)
                            <option value="{{ $z['id'] }}">
                                {{ \Intranet\Modules\Schulzeugnis\Support\Linear\LinearQuelle::zeitraumName($z) }}
                                (ID {{ $z['id'] }}, {{ $z['schueler_zahl'] }} Schüler)
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-400">Neueste zuerst. Gespeichert wird die Verknüpfung erst beim Import.</p>
                </div>
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Vorschau anzeigen
                </button>
            @endif
        </form>
    </div>
</x-app-layout>
