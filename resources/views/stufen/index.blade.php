<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-module-icon name="layer" class="text-2xl text-indigo-600" />
                <h1 class="text-xl font-semibold text-gray-800">Schulstufen</h1>
            </div>
            <a href="{{ route('module.schulzeugnis.stufen.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                + Neue Stufe
            </a>
        </div>
    </x-slot>

    <div class="max-w-3xl space-y-3">
        {{-- Erfolgsmeldungen rendert das Core-Layout global – hier nur Fehler. --}}
        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
                {{ session('error') }}
            </div>
        @endif

        <p class="text-sm text-gray-500">
            Jede Klasse gehört zu einer Schulstufe; deren Farbe bestimmt die Türfarbe in den
            <a href="{{ route('module.schulzeugnis.klassenraeume.index') }}" class="text-indigo-600 hover:text-indigo-700">Klassenräumen</a>.
        </p>

        @forelse ($stufen as $stufe)
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4">
                <div class="flex items-center gap-3">
                    <span class="w-6 text-center text-xs text-gray-400">{{ $stufe->reihenfolge }}</span>
                    <span class="inline-block h-8 w-8 rounded-lg ring-1 ring-black/10"
                          style="background: {{ $stufe->farbe }}"></span>
                    <div>
                        <h2 class="font-semibold text-gray-800">{{ $stufe->name }}</h2>
                        <div class="text-xs text-gray-400">
                            {{ $stufe->farbe }}
                            &middot; {{ $stufe->klassen_count }} {{ $stufe->klassen_count === 1 ? 'Klasse' : 'Klassen' }}
                            @if ($stufe->klassenBereich())
                                &middot; <span class="text-gray-500">{{ $stufe->klassenBereich() }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('module.schulzeugnis.stufen.edit', $stufe) }}"
                       class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-50">
                        Bearbeiten
                    </a>
                    <form method="POST" action="{{ route('module.schulzeugnis.stufen.destroy', $stufe) }}"
                          onsubmit="return confirm('Schulstufe {{ $stufe->name }} wirklich löschen?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="rounded-lg border border-red-200 px-2.5 py-1.5 text-sm text-red-600 hover:bg-red-50">
                            Löschen
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                Noch keine Schulstufe angelegt.
            </div>
        @endforelse
    </div>
</x-app-layout>
