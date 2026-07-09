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
                <label for="stufe_id" class="block text-sm font-medium text-gray-700">Schulstufe</label>
                <select name="stufe_id" id="stufe_id"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— keine Stufe —</option>
                    @foreach ($stufen as $stufe)
                        <option value="{{ $stufe->id }}" @selected(old('stufe_id', $klasse->stufe_id) == $stufe->id)>
                            {{ $stufe->name }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-400">Bestimmt die Türfarbe in den Klassenräumen.
                    @if ($stufen->isEmpty())
                        <a href="{{ route('module.schulzeugnis.stufen.create') }}" class="text-indigo-600 hover:text-indigo-700">Erst eine Stufe anlegen</a>.
                    @endif
                </p>
                @error('stufe_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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

            <div>
                <label for="klassenlehrer_id" class="block text-sm font-medium text-gray-700">Klassenlehrer</label>
                <select name="klassenlehrer_id" id="klassenlehrer_id"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— kein Klassenlehrer —</option>
                    @foreach ($lehrer as $l)
                        <option value="{{ $l->id }}" @selected(old('klassenlehrer_id', $klasse->klassenlehrer_id) == $l->id)>
                            {{ $l->fullName() }}
                        </option>
                    @endforeach
                </select>
                @if ($lehrer->isEmpty())
                    <p class="mt-1 text-xs text-amber-600">In diesem Schuljahr sind noch keine Lehrer angelegt.</p>
                @endif
                @error('klassenlehrer_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

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
