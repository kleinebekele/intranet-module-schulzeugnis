<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="category" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">
                {{ $format->exists ? 'Format bearbeiten' : 'Neues Zeugnisformat' }}
            </h1>
        </div>
    </x-slot>

    <div class="max-w-xl">
        <form method="POST"
              action="{{ $format->exists
                    ? route('module.schulzeugnis.formate.update', $format)
                    : route('module.schulzeugnis.formate.store') }}"
              class="space-y-5 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @if ($format->exists)
                @method('PUT')
            @endif

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                <input type="text" name="name" id="name"
                       value="{{ old('name', $format->name) }}"
                       placeholder="z. B. Textzeugnis Unterstufe" required
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="typ" class="block text-sm font-medium text-gray-700">Typ</label>
                <select name="typ" id="typ"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="text" @selected(old('typ', $format->typ ?? 'text') === 'text')>Textzeugnis (Freitext)</option>
                    <option value="noten" @selected(old('typ', $format->typ) === 'noten')>Notenzeugnis (1–6)</option>
                </select>
                @error('typ') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="seitenformat" class="block text-sm font-medium text-gray-700">Papierformat</label>
                    <select name="seitenformat" id="seitenformat"
                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="a4" @selected(old('seitenformat', $format->seitenformat ?? 'a4') === 'a4')>DIN A4</option>
                        <option value="a3" @selected(old('seitenformat', $format->seitenformat) === 'a3')>DIN A3</option>
                    </select>
                </div>
                <div>
                    <label for="ausrichtung" class="block text-sm font-medium text-gray-700">Ausrichtung</label>
                    <select name="ausrichtung" id="ausrichtung"
                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="hoch" @selected(old('ausrichtung', $format->ausrichtung ?? 'hoch') === 'hoch')>Hochformat</option>
                        <option value="quer" @selected(old('ausrichtung', $format->ausrichtung) === 'quer')>Querformat</option>
                    </select>
                </div>
            </div>

            <div class="rounded-lg bg-gray-50 px-3 py-3">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="broschuere" value="1"
                           @checked(old('broschuere', $format->exists ? $format->broschuere : false))
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm text-gray-700">Als gefaltete DIN-A3-Broschüre ausgeben (4 A4-Seiten)</span>
                </label>
                <p class="mt-1 text-xs text-gray-400">Im Broschüren-Modus sind die Seiten A4; Papierformat und Ausrichtung oben werden dann ignoriert.</p>
            </div>

            <div>
                <label for="beschreibung" class="block text-sm font-medium text-gray-700">Beschreibung <span class="text-gray-400">(optional)</span></label>
                <textarea name="beschreibung" id="beschreibung" rows="2"
                          class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('beschreibung', $format->beschreibung) }}</textarea>
                @error('beschreibung') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2">
                <input type="checkbox" name="aktiv" value="1"
                       @checked(old('aktiv', $format->exists ? $format->aktiv : true))
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Aktiv (wird für Klassen/Schüler angeboten)</span>
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Speichern
                </button>
                <a href="{{ route('module.schulzeugnis.formate.index') }}"
                   class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
            </div>
        </form>
    </div>
</x-app-layout>
