<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="calendar" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">
                {{ $schuljahr->exists ? 'Schuljahr bearbeiten' : 'Neues Schuljahr' }}
            </h1>
        </div>
    </x-slot>

    <div class="max-w-xl">
        <form method="POST"
              action="{{ $schuljahr->exists
                    ? route('module.schulzeugnis.schuljahre.update', $schuljahr)
                    : route('module.schulzeugnis.schuljahre.store') }}"
              class="space-y-5 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @if ($schuljahr->exists)
                @method('PUT')
            @endif

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Bezeichnung</label>
                <input type="text" name="name" id="name"
                       value="{{ old('name', $schuljahr->name) }}"
                       placeholder="z. B. 2026/2027" required
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700">Beginn</label>
                    <input type="date" name="start_date" id="start_date"
                           value="{{ old('start_date', $schuljahr->start_date?->format('Y-m-d')) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('start_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700">Ende</label>
                    <input type="date" name="end_date" id="end_date"
                           value="{{ old('end_date', $schuljahr->end_date?->format('Y-m-d')) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('end_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="eingabe_frist" class="block text-sm font-medium text-gray-700">Frist Dateneingabe</label>
                    <input type="date" name="eingabe_frist" id="eingabe_frist"
                           value="{{ old('eingabe_frist', $schuljahr->eingabe_frist?->format('Y-m-d')) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-400">Bis wann müssen Texte/Noten eingepflegt sein.</p>
                    @error('eingabe_frist') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="ausgabe_datum" class="block text-sm font-medium text-gray-700">Zeugnisausgabe</label>
                    <input type="date" name="ausgabe_datum" id="ausgabe_datum"
                           value="{{ old('ausgabe_datum', $schuljahr->ausgabe_datum?->format('Y-m-d')) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-400">Tag der Zeugnisausgabe.</p>
                    @error('ausgabe_datum') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1"
                       @checked(old('is_active', $schuljahr->is_active))
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Als aktives Schuljahr setzen (Standard-Ansicht)</span>
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Speichern
                </button>
                <a href="{{ route('module.schulzeugnis.schuljahre.index') }}"
                   class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
            </div>
        </form>
    </div>
</x-app-layout>
