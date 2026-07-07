<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="user" class="text-2xl text-indigo-600" />
            <div>
                <h1 class="text-xl font-semibold text-gray-800">
                    {{ $lehrer->exists ? 'Lehrer bearbeiten' : 'Neuer Lehrer' }}
                </h1>
                <p class="text-sm text-gray-500">Schuljahr {{ $schuljahr->name }}</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-xl">
        <form method="POST"
              action="{{ $lehrer->exists
                    ? route('module.schulzeugnis.lehrer.update', $lehrer)
                    : route('module.schulzeugnis.lehrer.store', $schuljahr) }}"
              class="space-y-5 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @if ($lehrer->exists)
                @method('PUT')
            @endif

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="vorname" class="block text-sm font-medium text-gray-700">Vorname</label>
                    <input type="text" name="vorname" id="vorname"
                           value="{{ old('vorname', $lehrer->vorname) }}" required
                           class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('vorname') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="nachname" class="block text-sm font-medium text-gray-700">Nachname</label>
                    <input type="text" name="nachname" id="nachname"
                           value="{{ old('nachname', $lehrer->nachname) }}" required
                           class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('nachname') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="core_user_id" class="block text-sm font-medium text-gray-700">Verknüpftes Benutzerkonto</label>
                <select name="core_user_id" id="core_user_id"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— kein Konto —</option>
                    @foreach ($benutzer as $b)
                        <option value="{{ $b->id }}" @selected(old('core_user_id', $lehrer->core_user_id) == $b->id)>
                            {{ $b->name }} ({{ $b->email }})
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-400">
                    Nur eine lose Verknüpfung (kein Fremdschlüssel): solange das Konto existiert, darf der Lehrer
                    im Modul zugreifen. Wird es gelöscht, bleiben die Zeugnis-Daten erhalten.
                </p>
                @error('core_user_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Speichern
                </button>
                <a href="{{ route('module.schulzeugnis.lehrer.index', $schuljahr) }}"
                   class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
            </div>
        </form>
    </div>
</x-app-layout>
