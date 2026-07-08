<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="user" class="text-2xl text-indigo-600" />
            <div>
                <h1 class="text-xl font-semibold text-gray-800">
                    {{ $schueler->exists ? 'Schüler bearbeiten' : 'Neuer Schüler' }}
                </h1>
                <p class="text-sm text-gray-500">Schuljahr {{ $schuljahr->name }}</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-xl">
        <form method="POST"
              action="{{ $schueler->exists
                    ? route('module.schulzeugnis.schueler.update', $schueler)
                    : route('module.schulzeugnis.schueler.store', $schuljahr) }}"
              class="space-y-5 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @if ($schueler->exists)
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="vorname" class="block text-sm font-medium text-gray-700">Vorname</label>
                    <input type="text" name="vorname" id="vorname"
                           value="{{ old('vorname', $schueler->vorname) }}" required
                           class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('vorname') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="nachname" class="block text-sm font-medium text-gray-700">Nachname</label>
                    <input type="text" name="nachname" id="nachname"
                           value="{{ old('nachname', $schueler->nachname) }}" required
                           class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('nachname') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="geburtsdatum" class="block text-sm font-medium text-gray-700">Geburtsdatum</label>
                    <input type="date" name="geburtsdatum" id="geburtsdatum"
                           value="{{ old('geburtsdatum', optional($schueler->geburtsdatum)->format('Y-m-d')) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('geburtsdatum') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="geburtsort" class="block text-sm font-medium text-gray-700">Geburtsort</label>
                    <input type="text" name="geburtsort" id="geburtsort"
                           value="{{ old('geburtsort', $schueler->geburtsort) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('geburtsort') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="geschlecht" class="block text-sm font-medium text-gray-700">Geschlecht</label>
                    <select name="geschlecht" id="geschlecht"
                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @php $g = old('geschlecht', $schueler->geschlecht); @endphp
                        <option value="" @selected($g === null || $g === '')>— ohne Angabe —</option>
                        <option value="w" @selected($g === 'w')>weiblich</option>
                        <option value="m" @selected($g === 'm')>männlich</option>
                        <option value="d" @selected($g === 'd')>divers</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-400">Für Anrede und Textbausteine.</p>
                    @error('geschlecht') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="quell_id" class="block text-sm font-medium text-gray-700">Quell-ID <span class="text-gray-400">(optional)</span></label>
                    <input type="text" name="quell_id" id="quell_id"
                           value="{{ old('quell_id', $schueler->quell_id) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-400">Stabile ID aus dem Quellsystem – verbindet dieselbe Person über die Jahre.</p>
                    @error('quell_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="klasse_id" class="block text-sm font-medium text-gray-700">Klasse</label>
                <select name="klasse_id" id="klasse_id"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— keine Klasse —</option>
                    @foreach ($klassen as $klasse)
                        <option value="{{ $klasse->id }}" @selected(old('klasse_id', $schueler->klasse_id) == $klasse->id)>
                            {{ $klasse->name }}
                        </option>
                    @endforeach
                </select>
                @if ($klassen->isEmpty())
                    <p class="mt-1 text-xs text-amber-600">In diesem Schuljahr gibt es noch keine Klassen – erst eine anlegen.</p>
                @endif
                @error('klasse_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="format_override_id" class="block text-sm font-medium text-gray-700">Abweichendes Zeugnisformat <span class="text-gray-400">(optional)</span></label>
                <select name="format_override_id" id="format_override_id"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— Standard der Klasse —</option>
                    @foreach ($formate as $format)
                        <option value="{{ $format->id }}" @selected(old('format_override_id', $schueler->format_override_id) == $format->id)>
                            {{ $format->name }} ({{ $format->typLabel() }}){{ $format->aktiv ? '' : ' · archiviert' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-400">Überschreibt das Standard-Format der Klasse nur für diesen Schüler.</p>
                @error('format_override_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Speichern
                </button>
                <a href="{{ route('module.schulzeugnis.schueler.index', $schuljahr) }}"
                   class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
            </div>
        </form>
    </div>
</x-app-layout>
