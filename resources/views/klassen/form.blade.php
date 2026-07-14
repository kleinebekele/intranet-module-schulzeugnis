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

    @php
        // "Allgemein" ist Pflicht: bei neuer Klasse als fixe erste Zeile vorbelegen.
        $bereicheListe = $bereiche->isNotEmpty()
            ? $bereiche
            : collect([(object) ['id' => null, 'name' => 'Allgemein']]);
        $hatFach   = (bool) old('hat_fachzeugnis', $klasse->exists ? $klasse->hat_fachzeugnis : true);
        $hatHaupt  = (bool) old('hat_hauptzeugnis', $klasse->exists ? $klasse->hat_hauptzeugnis : false);
        $hatSpruch = (bool) old('hat_zeugnisspruch', $klasse->exists ? $klasse->hat_zeugnisspruch : false);
    @endphp

    <div class="max-w-2xl">
        <form method="POST"
              action="{{ $klasse->exists
                    ? route('module.schulzeugnis.klassen.update', $klasse)
                    : route('module.schulzeugnis.klassen.store', $schuljahr) }}"
              class="space-y-6 rounded-xl border border-gray-200 bg-white p-6">
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

            {{-- ============ Fachzeugnis (Fächer) ============ --}}
            <div class="rounded-lg border border-gray-200 p-4">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="hat_fachzeugnis" id="hat_fachzeugnis" value="1" @checked($hatFach)
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm font-semibold text-gray-800">Fachzeugnis (Fächer)</span>
                </label>
                <p class="mt-1 text-xs text-gray-500">Das bisherige Zeugnis mit einer Spalte je Fach.</p>

                <div id="fach-details" class="mt-3 {{ $hatFach ? '' : 'hidden' }}">
                    <label for="standard_format_id" class="block text-sm font-medium text-gray-700">Fachzeugnis-Format</label>
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
            </div>

            {{-- ============ Hauptzeugnis (Fachbereiche) ============ --}}
            <div class="rounded-lg border border-gray-200 p-4">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="hat_hauptzeugnis" id="hat_hauptzeugnis" value="1" @checked($hatHaupt)
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm font-semibold text-gray-800">Hauptzeugnis (Fachbereiche)</span>
                </label>
                <p class="mt-1 text-xs text-gray-500">Eigenständiges Zeugnis aus benannten Fachbereichen (z. B. Allgemein, Rechnen …), die zu einem Text zusammenfließen.</p>

                <div id="haupt-details" class="mt-3 space-y-4 {{ $hatHaupt ? '' : 'hidden' }}">
                    <div>
                        <label for="hauptzeugnis_format_id" class="block text-sm font-medium text-gray-700">Hauptzeugnis-Format</label>
                        <select name="hauptzeugnis_format_id" id="hauptzeugnis_format_id"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— kein Format —</option>
                            @foreach ($formate as $format)
                                <option value="{{ $format->id }}" @selected(old('hauptzeugnis_format_id', $klasse->hauptzeugnis_format_id) == $format->id)>
                                    {{ $format->name }} ({{ $format->typLabel() }}){{ $format->aktiv ? '' : ' · archiviert' }}
                                </option>
                            @endforeach
                        </select>
                        @error('hauptzeugnis_format_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div id="fachbereiche" style="scroll-margin-top: 6rem;" class="rounded-lg p-2 -m-2">
                        <span class="block text-sm font-medium text-gray-700">Fachbereiche</span>
                        <p class="mt-0.5 text-xs text-gray-400">Reihenfolge = Abfolge im Zeugnis. „Allgemein" ist Pflicht.</p>
                        <div id="bereich-liste" class="mt-2 space-y-2">
                            @foreach ($bereicheListe as $i => $b)
                                @php $istAllgemein = $b->name === 'Allgemein'; @endphp
                                <div class="kb-bereich flex items-center gap-2">
                                    @if ($b->id)
                                        <input type="hidden" name="bereiche[{{ $i }}][id]" value="{{ $b->id }}">
                                    @endif
                                    <input type="text" name="bereiche[{{ $i }}][name]" value="{{ $b->name }}"
                                           {{ $istAllgemein ? 'readonly' : '' }} placeholder="Bereich (z. B. Rechnen)"
                                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 {{ $istAllgemein ? 'bg-gray-50 text-gray-500' : '' }}">
                                    @if ($istAllgemein)
                                        <span class="shrink-0 text-xs text-gray-400">Pflicht</span>
                                    @else
                                        <button type="button" class="kb-remove shrink-0 rounded-lg border border-gray-300 px-2 py-1.5 text-gray-500 hover:bg-red-50 hover:text-red-600" title="Bereich entfernen">
                                            <i class="bx bx-x text-lg"></i>
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <button type="button" id="bereich-add"
                                class="mt-2 inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-sm font-medium text-indigo-700 hover:bg-indigo-100">
                            <i class="bx bx-plus"></i> Fachbereich hinzufügen
                        </button>
                    </div>
                </div>
            </div>

            {{-- ============ Zeugnisspruch ============ --}}
            <div class="rounded-lg border border-gray-200 p-4">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="hat_zeugnisspruch" id="hat_zeugnisspruch" value="1" @checked($hatSpruch)
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm font-semibold text-gray-800">Zeugnisspruch</span>
                </label>
                <p class="mt-1 text-xs text-gray-500">
                    Jeder Schüler erhält einen Spruch (in der Übersicht wie ein Fach). Nur der Klassenlehrer kann ihn setzen –
                    Auswahl aus dem <a href="{{ route('module.schulzeugnis.sprueche.index') }}" class="text-indigo-600 hover:text-indigo-700">Spruch-Katalog</a>, danach frei editierbar.
                </p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Speichern
                </button>
                <a href="{{ route('module.schulzeugnis.klassen.jahr', $schuljahr) }}"
                   class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
            </div>
        </form>
    </div>

    <style>
        .fb-highlight {
            outline: 3px solid #f59e0b;
            outline-offset: 4px;
            background: #fffbeb;
            animation: fb-pulse 0.9s ease-in-out 3;
        }
        @keyframes fb-pulse {
            0%, 100% { outline-color: #f59e0b; }
            50%      { outline-color: rgba(245, 158, 11, .25); }
        }
    </style>

    <script>
        (function () {
            // Detail-Bereiche je Schalter ein-/ausblenden.
            function bind(cbId, boxId) {
                var cb = document.getElementById(cbId), box = document.getElementById(boxId);
                if (!cb || !box) { return; }
                cb.addEventListener('change', function () { box.classList.toggle('hidden', !cb.checked); });
            }
            bind('hat_fachzeugnis', 'fach-details');
            bind('hat_hauptzeugnis', 'haupt-details');

            // Ankersprung #fachbereiche (aus dem Zeugnis-Hinweis): Box aufklappen,
            // hinscrollen und kurz umrahmen, damit klar ist, was gemeint ist.
            if (window.location.hash === '#fachbereiche') {
                var ziel = document.getElementById('fachbereiche');
                var box  = document.getElementById('haupt-details');
                if (box) { box.classList.remove('hidden'); }
                if (ziel) {
                    setTimeout(function () {
                        ziel.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        ziel.classList.add('fb-highlight');
                        setTimeout(function () { ziel.classList.remove('fb-highlight'); }, 3000);
                    }, 120);
                }
            }

            // Fachbereiche dynamisch hinzufügen/entfernen. Index läuft fortlaufend weiter.
            var liste = document.getElementById('bereich-liste');
            var addBtn = document.getElementById('bereich-add');
            var next = liste ? liste.querySelectorAll('.kb-bereich').length : 0;

            if (addBtn && liste) {
                addBtn.addEventListener('click', function () {
                    var row = document.createElement('div');
                    row.className = 'kb-bereich flex items-center gap-2';
                    row.innerHTML =
                        '<input type="text" name="bereiche[' + next + '][name]" placeholder="Bereich (z. B. Rechnen)" ' +
                        'class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">' +
                        '<button type="button" class="kb-remove shrink-0 rounded-lg border border-gray-300 px-2 py-1.5 text-gray-500 hover:bg-red-50 hover:text-red-600" title="Bereich entfernen"><i class="bx bx-x text-lg"></i></button>';
                    liste.appendChild(row);
                    var inp = row.querySelector('input');
                    if (inp) { inp.focus(); }
                    next++;
                });
            }

            if (liste) {
                liste.addEventListener('click', function (e) {
                    var btn = e.target.closest('.kb-remove');
                    if (btn) { btn.closest('.kb-bereich').remove(); }
                });
            }
        })();
    </script>
</x-app-layout>
