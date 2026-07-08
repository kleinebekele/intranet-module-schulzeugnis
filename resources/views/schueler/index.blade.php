<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-module-icon name="user" class="text-2xl text-indigo-600" />
                <div>
                    <h1 class="text-xl font-semibold text-gray-800">Schüler</h1>
                    <p class="text-sm text-gray-500">Schuljahr {{ $schuljahr->name }}</p>
                </div>
            </div>
            <a href="{{ route('module.schulzeugnis.schueler.create', $schuljahr) }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                + Neuer Schüler
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

        @if ($schueler->isNotEmpty())
            <p class="text-sm text-gray-500">{{ $schueler->count() }} {{ $schueler->count() === 1 ? 'Schüler' : 'Schüler' }}</p>
        @endif

        @forelse ($schueler as $s)
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg font-semibold text-gray-800">{{ $s->nachname }}, {{ $s->vorname }}</h2>
                        @if ($s->klasse)
                            <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ $s->klasse->name }}</span>
                        @else
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">keine Klasse</span>
                        @endif
                    </div>
                    <p class="mt-0.5 text-sm text-gray-500">
                        @if ($s->geburtsdatum)
                            geboren am {{ $s->geburtsdatum->format('d.m.Y') }}{{ $s->geburtsort ? ' in ' . $s->geburtsort : '' }}
                        @elseif ($s->geburtsort)
                            geboren in {{ $s->geburtsort }}
                        @else
                            <span class="italic text-gray-400">kein Geburtsdatum</span>
                        @endif
                        @if ($s->quell_id)
                            &middot; Quell-ID {{ $s->quell_id }}
                        @endif
                    </p>
                    @if ($s->formatOverride)
                        <p class="mt-0.5 text-sm text-amber-700">Eigenes Format: {{ $s->formatOverride->name }}</p>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('module.schulzeugnis.schueler.edit', $s) }}"
                       class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-50">
                        Bearbeiten
                    </a>
                    <form method="POST" action="{{ route('module.schulzeugnis.schueler.destroy', $s) }}"
                          onsubmit="return confirm('{{ $s->fullName() }} wirklich löschen?');">
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
                Noch kein Schüler in diesem Schuljahr. Lege den ersten an oder importiere sie später.
            </div>
        @endforelse
    </div>
</x-app-layout>
