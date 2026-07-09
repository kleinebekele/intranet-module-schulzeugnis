<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="list" class="text-2xl text-indigo-600" />
            <div>
                <h1 class="text-xl font-semibold text-gray-800">Lehraufträge</h1>
                <p class="text-sm text-gray-500">Klasse {{ $klasse->name }} &middot; Schuljahr {{ $klasse->schuljahr->name }}</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-2xl space-y-4">
        <a href="{{ route('module.schulzeugnis.klassen.jahr', $klasse->schuljahr_id) }}"
           class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
            &larr; Zurück zu den Klassen
        </a>

        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
                {{ session('error') }}
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-600">
            Klassenlehrer:
            @if ($klasse->klassenlehrer)
                <strong class="text-gray-800">{{ $klasse->klassenlehrer->fullName() }}</strong>
            @else
                <span class="italic text-gray-400">nicht gesetzt</span>
            @endif
            <span class="text-gray-400"> &middot; </span>
            <a href="{{ route('module.schulzeugnis.klassen.edit', $klasse) }}" class="text-indigo-600 hover:text-indigo-700">an der Klasse ändern</a>
        </div>

        {{-- Neuen Lehrauftrag hinzufügen --}}
        <form method="POST" action="{{ route('module.schulzeugnis.klassen.lehrauftraege.store', $klasse) }}"
              class="rounded-xl border border-gray-200 bg-white p-5">
            @csrf
            <h2 class="text-sm font-semibold text-gray-700">Lehrauftrag hinzufügen</h2>

            @if ($faecher->isEmpty() || $lehrerListe->isEmpty())
                <p class="mt-2 text-sm text-amber-600">
                    @if ($faecher->isEmpty()) Es sind noch keine Fächer angelegt. @endif
                    @if ($lehrerListe->isEmpty()) In diesem Schuljahr sind noch keine Lehrer angelegt. @endif
                </p>
            @else
                <div class="mt-3 flex flex-wrap items-end gap-3">
                    <label class="flex-1 min-w-[10rem] text-sm">
                        <span class="block font-medium text-gray-700">Fach</span>
                        <select name="fach_id" required
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($faecher as $fach)
                                <option value="{{ $fach->id }}" @selected(old('fach_id') == $fach->id)>{{ $fach->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="flex-1 min-w-[10rem] text-sm">
                        <span class="block font-medium text-gray-700">Lehrer</span>
                        <select name="lehrer_id" required
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($lehrerListe as $lehrer)
                                <option value="{{ $lehrer->id }}" @selected(old('lehrer_id') == $lehrer->id)>{{ $lehrer->fullName() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Hinzufügen
                    </button>
                </div>
                <p class="mt-2 text-xs text-gray-400">Mehrere Lehrer je Fach sind möglich (Team-Teaching).</p>
            @endif
            @error('fach_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            @error('lehrer_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </form>

        {{-- Bestehende Lehraufträge --}}
        <div class="space-y-2">
            @forelse ($lehrauftraege as $la)
                <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-5 py-3">
                    <div class="text-sm">
                        <span class="font-semibold text-gray-800">{{ $la->fach?->name ?? '—' }}</span>
                        <span class="text-gray-400"> &middot; </span>
                        <span class="text-gray-600">{{ $la->lehrer?->fullName() ?: '—' }}</span>
                    </div>
                    <form method="POST" action="{{ route('module.schulzeugnis.klassen.lehrauftraege.destroy', $la) }}"
                          onsubmit="return confirm('Lehrauftrag {{ $la->fach?->name }} · {{ $la->lehrer?->fullName() }} entfernen?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="rounded-lg border border-red-200 px-2.5 py-1.5 text-sm text-red-600 hover:bg-red-50">
                            Entfernen
                        </button>
                    </form>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                    Noch keine Lehraufträge für diese Klasse.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
