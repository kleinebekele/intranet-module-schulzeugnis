<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-module-icon name="list" class="text-2xl text-indigo-600" />
                <h1 class="text-xl font-semibold text-gray-800">Fächer</h1>
            </div>
            <a href="{{ route('module.schulzeugnis.faecher.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                + Neues Fach
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

        @forelse ($faecher as $fach)
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 {{ $fach->aktiv ? '' : 'opacity-60' }}">
                <div class="flex items-center gap-3">
                    <span class="w-8 text-center text-xs text-gray-400">{{ $fach->reihenfolge }}</span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-semibold text-gray-800">{{ $fach->name }}</h2>
                            @if ($fach->kuerzel)
                                <span class="text-xs text-gray-400">{{ $fach->kuerzel }}</span>
                            @endif
                            @unless ($fach->aktiv)
                                <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600">archiviert</span>
                            @endunless
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('module.schulzeugnis.faecher.edit', $fach) }}"
                       class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-50">
                        Bearbeiten
                    </a>
                    <form method="POST" action="{{ route('module.schulzeugnis.faecher.toggle', $fach) }}">
                        @csrf
                        <button type="submit"
                                class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-50">
                            {{ $fach->aktiv ? 'Archivieren' : 'Reaktivieren' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('module.schulzeugnis.faecher.destroy', $fach) }}"
                          onsubmit="return confirm('Fach {{ $fach->name }} wirklich löschen?');">
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
                Noch kein Fach angelegt. Lege die feste Fächerliste an (Deutsch, Eurythmie, …).
            </div>
        @endforelse
    </div>
</x-app-layout>
