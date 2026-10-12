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
        // Klassenleitung: mindestens eine (ggf. leere) Auswahlzeile.
        $klassenlehrerIds = array_values(array_filter((array) old('klassenlehrer_ids', $klasse->exists ? $klasse->klassenlehrerIds()->all() : [])));
        if ($klassenlehrerIds === []) {
            $klassenlehrerIds = [null];
        }
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
                <span class="block text-sm font-medium text-gray-700">Klassenlehrer</span>
                {{-- Suchauswahl je Zeile: Textfeld filtert die Lehrer, das versteckte Feld trägt die ID. --}}
                <div id="kl-liste" class="mt-1 space-y-2">
                    @foreach ($klassenlehrerIds as $klId)
                        @php $klLehrer = $klId ? $lehrer->firstWhere('id', (int) $klId) : null; @endphp
                        <div class="kl-zeile flex items-center gap-2">
                            <div class="kl-feld">
                                <input type="hidden" name="klassenlehrer_ids[]" class="kl-wert" value="{{ $klLehrer?->id }}">
                                <input type="text" class="kl-suche block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                       value="{{ $klLehrer?->fullName() }}" placeholder="Lehrer suchen – Namen eintippen …" autocomplete="off"
                                       role="combobox" aria-expanded="false">
                                <ul class="kl-treffer" role="listbox" hidden></ul>
                            </div>
                            <button type="button" class="kl-remove shrink-0 rounded-lg border border-gray-300 px-2 py-1.5 text-gray-500 hover:bg-red-50 hover:text-red-600" title="Klassenlehrer entfernen" @if ($loop->count < 2) hidden @endif>
                                <i class="bx bx-x text-lg"></i>
                            </button>
                        </div>
                    @endforeach
                </div>
                <button type="button" id="kl-add" hidden
                        class="mt-2 items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-sm font-medium text-indigo-700 hover:bg-indigo-100">
                    <i class="bx bx-plus"></i> Weiteren Klassenlehrer wählen
                </button>
                @if ($lehrer->isEmpty())
                    <p class="mt-1 text-xs text-amber-600">In diesem Schuljahr sind noch keine Lehrer angelegt.</p>
                @endif
                @error('klassenlehrer_ids') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @error('klassenlehrer_ids.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
            outline: 3px solid var(--color-amber-500, #f59e0b);
            outline-offset: 4px;
            background: var(--color-amber-50, #fffbeb);
            animation: fb-pulse 0.9s ease-in-out 3;
        }
        @keyframes fb-pulse {
            0%, 100% { outline-color: var(--color-amber-500, #f59e0b); }
            50%      { outline-color: rgba(245, 158, 11, .25); }
        }

        /* Klassenlehrer-Suchauswahl */
        #kl-add:not([hidden]) { display: inline-flex; }
        .kl-remove[hidden], .kl-treffer[hidden] { display: none; }
        .kl-feld { position: relative; flex: 1 1 auto; min-width: 0; }
        .kl-treffer {
            position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 30;
            max-height: 16rem; overflow-y: auto; margin: 0; padding: 4px; list-style: none;
            background: var(--color-white, #fff);
            border: 1px solid var(--color-gray-200, #e5e7eb); border-radius: .5rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, .25);
        }
        .kl-treffer li { padding: .4rem .6rem; border-radius: .375rem; cursor: pointer; font-size: .875rem; color: var(--color-gray-800, #1f2937); }
        .kl-treffer li.aktiv, .kl-treffer li:hover { background: var(--color-indigo-50, #eef2ff); color: var(--color-indigo-700, #4338ca); }
        .kl-treffer li.leer { cursor: default; color: var(--color-gray-400, #9ca3af); background: none; }
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

            // Klassenleitung: Suchauswahl je Zeile; „Weiteren Klassenlehrer wählen" erscheint,
            // sobald die letzte Zeile belegt ist – beliebig viele Zeilen.
            var klListe = document.getElementById('kl-liste'), klAdd = document.getElementById('kl-add');
            var klLehrer = @js($lehrer->map(fn ($l) => ['id' => $l->id, 'name' => $l->fullName()])->values());
            if (klListe && klAdd) {
                var klZeilen = function () { return klListe.querySelectorAll('.kl-zeile'); };
                var klAktualisieren = function () {
                    var zeilen = klZeilen(), letzte = zeilen[zeilen.length - 1];
                    klAdd.hidden = !letzte || !letzte.querySelector('.kl-wert').value;
                    zeilen.forEach(function (z) { z.querySelector('.kl-remove').hidden = zeilen.length < 2; });
                };
                var klNorm = function (s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };

                // Liste füllen: Treffer auf alle Wortteile, ohne die in anderen Zeilen gewählten.
                var klZeigen = function (zeile) {
                    var suche = zeile.querySelector('.kl-suche'), liste = zeile.querySelector('.kl-treffer');
                    var belegt = Array.prototype.map.call(klZeilen(), function (z) { return z === zeile ? '' : z.querySelector('.kl-wert').value; });
                    var teile = klNorm(suche.value.trim()).split(/\s+/).filter(Boolean);
                    var treffer = klLehrer.filter(function (l) {
                        var n = klNorm(l.name);
                        return belegt.indexOf(String(l.id)) === -1 && teile.every(function (t) { return n.indexOf(t) !== -1; });
                    });
                    liste.innerHTML = '';
                    treffer.forEach(function (l, i) {
                        var li = document.createElement('li');
                        li.textContent = l.name;
                        li.dataset.id = l.id;
                        li.setAttribute('role', 'option');
                        if (i === 0) { li.classList.add('aktiv'); }
                        liste.appendChild(li);
                    });
                    if (!treffer.length) {
                        var leer = document.createElement('li');
                        leer.className = 'leer';
                        leer.textContent = 'Kein Lehrer gefunden';
                        liste.appendChild(leer);
                    }
                    liste.hidden = false;
                    suche.setAttribute('aria-expanded', 'true');
                };
                var klSchliessen = function (zeile) {
                    var suche = zeile.querySelector('.kl-suche'), wert = zeile.querySelector('.kl-wert');
                    zeile.querySelector('.kl-treffer').hidden = true;
                    suche.setAttribute('aria-expanded', 'false');
                    // Freitext ohne Auswahl zurück auf den gewählten Namen (leer = keiner).
                    var gewaehlt = klLehrer.find(function (l) { return String(l.id) === wert.value; });
                    if (suche.value.trim() === '') { wert.value = ''; }
                    else { suche.value = gewaehlt ? gewaehlt.name : ''; if (!gewaehlt) { wert.value = ''; } }
                    klAktualisieren();
                };
                var klWaehlen = function (zeile, li) {
                    if (!li || !li.dataset.id) { return; }
                    zeile.querySelector('.kl-wert').value = li.dataset.id;
                    zeile.querySelector('.kl-suche').value = li.textContent;
                    klSchliessen(zeile);
                };

                klListe.addEventListener('focusin', function (e) {
                    if (e.target.classList.contains('kl-suche')) { e.target.select(); klZeigen(e.target.closest('.kl-zeile')); }
                });
                klListe.addEventListener('input', function (e) {
                    if (e.target.classList.contains('kl-suche')) { klZeigen(e.target.closest('.kl-zeile')); }
                });
                klListe.addEventListener('focusout', function (e) {
                    if (e.target.classList.contains('kl-suche')) {
                        var zeile = e.target.closest('.kl-zeile');
                        setTimeout(function () { if (!zeile.contains(document.activeElement)) { klSchliessen(zeile); } }, 150);
                    }
                });
                klListe.addEventListener('keydown', function (e) {
                    if (!e.target.classList.contains('kl-suche')) { return; }
                    var zeile = e.target.closest('.kl-zeile'), liste = zeile.querySelector('.kl-treffer');
                    var eintraege = Array.prototype.slice.call(liste.querySelectorAll('li[data-id]'));
                    var pos = eintraege.findIndex(function (li) { return li.classList.contains('aktiv'); });
                    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                        e.preventDefault();
                        if (liste.hidden) { klZeigen(zeile); return; }
                        if (!eintraege.length) { return; }
                        var neu = e.key === 'ArrowDown' ? Math.min(pos + 1, eintraege.length - 1) : Math.max(pos - 1, 0);
                        eintraege.forEach(function (li, i) { li.classList.toggle('aktiv', i === neu); });
                        eintraege[neu].scrollIntoView({ block: 'nearest' });
                    } else if (e.key === 'Enter') {
                        if (!liste.hidden) { e.preventDefault(); klWaehlen(zeile, eintraege[pos]); }
                    } else if (e.key === 'Escape') {
                        klSchliessen(zeile);
                    }
                });
                // mousedown statt click: läuft vor dem focusout des Textfelds.
                klListe.addEventListener('mousedown', function (e) {
                    var li = e.target.closest('.kl-treffer li');
                    if (li) { e.preventDefault(); klWaehlen(li.closest('.kl-zeile'), li); }
                });

                klAdd.addEventListener('click', function () {
                    var zeilen = klZeilen(), neu = zeilen[zeilen.length - 1].cloneNode(true);
                    neu.querySelector('.kl-wert').value = '';
                    neu.querySelector('.kl-suche').value = '';
                    neu.querySelector('.kl-treffer').hidden = true;
                    klListe.appendChild(neu);
                    klAktualisieren();
                    neu.querySelector('.kl-suche').focus();
                });
                klListe.addEventListener('click', function (e) {
                    var btn = e.target.closest('.kl-remove');
                    if (btn && klZeilen().length > 1) { btn.closest('.kl-zeile').remove(); klAktualisieren(); }
                });
                klAktualisieren();
            }

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
