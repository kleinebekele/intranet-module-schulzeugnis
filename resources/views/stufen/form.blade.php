<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="category" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">
                {{ $stufe->exists ? 'Schulstufe bearbeiten' : 'Neue Schulstufe' }}
            </h1>
        </div>
    </x-slot>

    <style>
        /* Mini-Türvorschau – gleicher Look wie in den Klassenräumen, aus einer Grundfarbe. */
        .st-vorschau {
            position: relative; width: 96px; height: 150px;
            border-radius: 7px 7px 3px 3px;
            background: linear-gradient(180deg, #efe9df, #e3dccf);
            padding: 7px 7px 0; box-shadow: 0 8px 14px -8px rgba(0,0,0,.45);
        }
        .st-blatt {
            position: absolute; inset: 7px 7px 0; border-radius: 4px 4px 0 0;
            background: linear-gradient(135deg,
                color-mix(in srgb, var(--st) 78%, white),
                var(--st) 55%,
                color-mix(in srgb, var(--st) 80%, black));
            box-shadow: inset 0 0 0 2px rgba(0,0,0,.08);
        }
        .st-panels {
            position: absolute; inset: 9px 9px 10px; display: grid;
            grid-template-columns: 1fr 1fr; grid-template-rows: 1.15fr 1fr; gap: 8px;
        }
        .st-panel {
            border-radius: 3px;
            background: linear-gradient(135deg, rgba(255,255,255,.10), rgba(0,0,0,.06));
            box-shadow: inset 2px 2px 4px rgba(0,0,0,.28), inset -2px -2px 4px rgba(255,255,255,.16);
        }
        .st-griff {
            position: absolute; right: 9px; top: 50%; width: 6px; height: 20px;
            transform: translateY(-50%); border-radius: 4px;
            background: linear-gradient(180deg, #f4d35e, #d9a521); box-shadow: 0 1px 2px rgba(0,0,0,.4);
        }
    </style>

    <div class="max-w-xl">
        <form method="POST"
              action="{{ $stufe->exists
                    ? route('module.schulzeugnis.stufen.update', $stufe)
                    : route('module.schulzeugnis.stufen.store') }}"
              class="space-y-5 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @if ($stufe->exists)
                @method('PUT')
            @endif

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                <input type="text" name="name" id="name"
                       value="{{ old('name', $stufe->name) }}"
                       placeholder="z. B. Sekundarstufe I" required
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-start gap-6">
                <div class="flex-1 space-y-4">
                    <div>
                        <label for="farbe" class="block text-sm font-medium text-gray-700">Türfarbe</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input type="color" name="farbe" id="farbe"
                                   value="{{ old('farbe', $stufe->farbe ?: '#6b7280') }}"
                                   class="h-10 w-14 cursor-pointer rounded-lg border border-gray-300 bg-white p-1">
                            <input type="text" id="farbe_hex" value="{{ old('farbe', $stufe->farbe ?: '#6b7280') }}"
                                   readonly
                                   class="w-28 rounded-lg border-gray-300 bg-gray-50 font-mono text-sm text-gray-600 shadow-sm">
                        </div>
                        <p class="mt-1 text-xs text-gray-400">Bestimmt die Türfarbe dieser Stufe in den Klassenräumen.</p>
                        @error('farbe') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="reihenfolge" class="block text-sm font-medium text-gray-700">Reihenfolge</label>
                        <input type="number" name="reihenfolge" id="reihenfolge" min="0"
                               value="{{ old('reihenfolge', $stufe->reihenfolge ?? 0) }}"
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-gray-400">Sortierung der Stufen (klein zuerst).</p>
                        @error('reihenfolge') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="pt-6 text-center">
                    <div class="st-vorschau" id="st-vorschau" style="--st: {{ old('farbe', $stufe->farbe ?: '#6b7280') }}">
                        <div class="st-blatt">
                            <div class="st-panels">
                                <div class="st-panel"></div><div class="st-panel"></div>
                                <div class="st-panel"></div><div class="st-panel"></div>
                            </div>
                            <div class="st-griff"></div>
                        </div>
                    </div>
                    <div class="mt-2 text-xs text-gray-400">Vorschau</div>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Speichern
                </button>
                <a href="{{ route('module.schulzeugnis.stufen.index') }}"
                   class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
            </div>
        </form>
    </div>

    <script>
        (function () {
            const picker  = document.getElementById('farbe');
            const hex     = document.getElementById('farbe_hex');
            const vorschau = document.getElementById('st-vorschau');
            picker.addEventListener('input', function () {
                hex.value = picker.value;
                vorschau.style.setProperty('--st', picker.value);
            });
        })();
    </script>
</x-app-layout>
