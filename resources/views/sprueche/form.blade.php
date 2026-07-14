<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="book" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">
                {{ $spruch->exists ? 'Zeugnisspruch bearbeiten' : 'Neuer Zeugnisspruch' }}
            </h1>
        </div>
    </x-slot>

    <div class="max-w-2xl">
        <form method="POST"
              action="{{ $spruch->exists
                    ? route('module.schulzeugnis.sprueche.update', $spruch)
                    : route('module.schulzeugnis.sprueche.store') }}"
              class="space-y-5 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @if ($spruch->exists)
                @method('PUT')
            @endif

            <div>
                <label for="titel" class="block text-sm font-medium text-gray-700">Titel / Herkunft <span class="text-gray-400">(optional)</span></label>
                <input type="text" name="titel" id="titel" value="{{ old('titel', $spruch->titel) }}"
                       placeholder="z. B. Christian Morgenstern"
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('titel') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="text" class="block text-sm font-medium text-gray-700">Spruch</label>
                <textarea name="text" id="text" rows="6" required
                          class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('text', $spruch->text) }}</textarea>
                @error('text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-end gap-6">
                <div>
                    <label for="reihenfolge" class="block text-sm font-medium text-gray-700">Reihenfolge</label>
                    <input type="number" name="reihenfolge" id="reihenfolge" min="0"
                           value="{{ old('reihenfolge', $spruch->reihenfolge ?? 0) }}"
                           class="mt-1 block w-28 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('reihenfolge') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <label class="flex items-center gap-2 pb-2 text-sm text-gray-700">
                    <input type="checkbox" name="aktiv" value="1" @checked(old('aktiv', $spruch->aktiv ?? true))
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    aktiv (im Katalog auswählbar)
                </label>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Speichern</button>
                <a href="{{ route('module.schulzeugnis.sprueche.index') }}"
                   class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
            </div>
        </form>
    </div>
</x-app-layout>
