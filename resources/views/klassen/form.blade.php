<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="users" class="text-2xl text-indigo-600" />
            <div>
                <h1 class="text-xl font-semibold text-gray-800">
                    {{ $klasse->exists ? 'Klasse bearbeiten' : 'Neue Klasse' }}
                </h1>
                <p class="text-sm text-gray-500">Schuljahr {{ $schuljahr->name }}</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-xl">
        <form method="POST"
              action="{{ $klasse->exists
                    ? route('module.schulzeugnis.klassen.update', $klasse)
                    : route('module.schulzeugnis.klassen.store', $schuljahr) }}"
              class="space-y-5 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @if ($klasse->exists)
                @method('PUT')
            @endif

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Bezeichnung</label>
                <input type="text" name="name" id="name"
                       value="{{ old('name', $klasse->name) }}"
                       placeholder="z. B. 5a" required
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="standard_format_id" class="block text-sm font-medium text-gray-700">Standard-Zeugnisformat</label>
                <select name="standard_format_id" id="standard_format_id"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— kein Standard —</option>
                    @foreach ($formate as $format)
                        <option value="{{ $format->id }}" @selected(old('standard_format_id', $klasse->standard_format_id) == $format->id)>
                            {{ $format->name }} ({{ $format->typLabel() }}){{ $format->aktiv ? '' : ' · archiviert' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-400">Vorgabe für alle Schüler der Klasse; je Schüler überschreibbar.</p>
                @error('standard_format_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <p class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500">
                Der Klassenlehrer lässt sich ergänzen, sobald Lehrer angelegt sind.
            </p>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Speichern
                </button>
                <a href="{{ route('module.schulzeugnis.klassen.index', $schuljahr) }}"
                   class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
            </div>
        </form>
    </div>
</x-app-layout>
