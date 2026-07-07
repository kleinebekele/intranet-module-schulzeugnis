<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="list" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">
                {{ $fach->exists ? 'Fach bearbeiten' : 'Neues Fach' }}
            </h1>
        </div>
    </x-slot>

    <div class="max-w-xl">
        <form method="POST"
              action="{{ $fach->exists
                    ? route('module.schulzeugnis.faecher.update', $fach)
                    : route('module.schulzeugnis.faecher.store') }}"
              class="space-y-5 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @if ($fach->exists)
                @method('PUT')
            @endif

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                <input type="text" name="name" id="name"
                       value="{{ old('name', $fach->name) }}"
                       placeholder="z. B. Deutsch" required
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="kuerzel" class="block text-sm font-medium text-gray-700">Kürzel</label>
                    <input type="text" name="kuerzel" id="kuerzel"
                           value="{{ old('kuerzel', $fach->kuerzel) }}"
                           placeholder="z. B. De"
                           class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('kuerzel') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="reihenfolge" class="block text-sm font-medium text-gray-700">Reihenfolge</label>
                    <input type="number" name="reihenfolge" id="reihenfolge" min="0"
                           value="{{ old('reihenfolge', $fach->reihenfolge ?? 0) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-400">Sortierung auf dem Zeugnis (klein zuerst).</p>
                    @error('reihenfolge') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <label class="flex items-center gap-2">
                <input type="checkbox" name="aktiv" value="1"
                       @checked(old('aktiv', $fach->exists ? $fach->aktiv : true))
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Aktiv (wird für neue Lehraufträge angeboten)</span>
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Speichern
                </button>
                <a href="{{ route('module.schulzeugnis.faecher.index') }}"
                   class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
            </div>
        </form>
    </div>
</x-app-layout>
