<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-module-icon name="users" class="text-2xl text-indigo-600" />
                <div>
                    <h1 class="text-xl font-semibold text-gray-800">Klassen</h1>
                    <p class="text-sm text-gray-500">Schuljahr {{ $schuljahr->name }}</p>
                </div>
            </div>
            <a href="{{ route('module.schulzeugnis.klassen.create', $schuljahr) }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                + Neue Klasse
            </a>
        </div>
    </x-slot>

    <div class="max-w-3xl space-y-4">
        <a href="{{ route('module.schulzeugnis.schuljahre.index') }}"
           class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
            &larr; Zurück zu den Schuljahren
        </a>

        {{-- Erfolgsmeldungen rendert das Core-Layout global – hier nur Fehler. --}}
        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
                {{ session('error') }}
            </div>
        @endif

        @forelse ($klassen as $klasse)
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">{{ $klasse->name }}</h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        @if ($klasse->standardFormat)
                            Standard: {{ $klasse->standardFormat->name }} ({{ $klasse->standardFormat->typLabel() }})
                        @else
                            <span class="italic text-gray-400">kein Standard-Format</span>
                        @endif
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('module.schulzeugnis.klassen.edit', $klasse) }}"
                       class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-50">
                        Bearbeiten
                    </a>
                    <form method="POST" action="{{ route('module.schulzeugnis.klassen.destroy', $klasse) }}"
                          onsubmit="return confirm('Klasse {{ $klasse->name }} wirklich löschen?');">
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
                Noch keine Klasse in diesem Schuljahr. Lege die erste an.
            </div>
        @endforelse
    </div>
</x-app-layout>
