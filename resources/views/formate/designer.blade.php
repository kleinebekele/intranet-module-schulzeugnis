<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <x-module-icon name="category" class="text-2xl text-indigo-600" />
                <div>
                    <h1 class="text-xl font-semibold text-gray-800">Designer &middot; {{ $format->name }}</h1>
                    <p class="text-sm text-gray-500">
                        {{ $format->broschuere
                            ? 'DIN A3 · gefaltete Broschüre (4 Seiten)'
                            : strtoupper($format->seitenformat) . ' · ' . ($format->ausrichtung === 'quer' ? 'Querformat' : 'Hochformat') }}
                        &middot; {{ $format->typLabel() }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span id="dz-status" class="text-sm text-gray-500"></span>
                <button id="dz-varhelp" type="button">
                    <span style="font-weight:700;">?</span> Variablen erklären
                </button>
                <a id="dz-vorschau" href="{{ route('module.schulzeugnis.formate.vorschau', $format) }}" target="_blank" title="Vorschau"
                   class="inline-flex items-center justify-center rounded-lg border border-indigo-200 p-2.5 text-2xl text-indigo-600 hover:bg-indigo-50"><i class="bx bx-show"></i></a>
                <a href="{{ route('module.schulzeugnis.formate.index') }}" title="Fertig"
                   class="inline-flex items-center justify-center rounded-lg border border-gray-300 p-2.5 text-2xl text-gray-600 hover:bg-gray-50"><i class="bx bx-check"></i></a>
                <button id="dz-save" type="button" title="Speichern"
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 p-2.5 text-2xl text-white hover:bg-indigo-700"><i class="bx bx-save"></i></button>
            </div>
        </div>
    </x-slot>

    <style>
        #dz-app { display: flex; gap: 16px; align-items: flex-start; }
        #dz-app .dz-side { width: 220px; flex: none; position: sticky; top: 16px; align-self: flex-start; }
        #dz-app .dz-canvas { flex: 1; overflow-x: auto; background: #f3f4f6; border-radius: 12px; padding: 24px; }
        #dz-pages { display: flex; flex-direction: column; gap: 22px; align-items: center; }
        .dz-pagelabel { font-size: 12px; color: #6b7280; text-align: center; }
        .dz-pagebar { display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 6px; }
        .dz-swap { font-size: 12px; border: 1px solid #d1d5db; border-radius: 6px; padding: 2px 6px; color: #4f46e5; background: #fff; cursor: pointer; }
        .dz-page { position: relative; background: #fff; box-shadow: 0 1px 8px rgba(0,0,0,.15); }
        .dz-page.dz-active { outline: 2px solid #a5b4fc; }
        .dz-page.dz-page-folge { outline: 2px dashed #c7d2fe; }
        .dz-page.dz-page-folge.dz-active { outline: 2px solid #a5b4fc; }
        .dz-folgetag { position: absolute; right: 6px; top: 6px; z-index: 7; background: #eef2ff; color: #4f46e5; font: 600 10px/1.2 sans-serif; padding: 2px 7px; border-radius: 999px; pointer-events: none; box-shadow: 0 1px 2px rgba(0,0,0,.12); }
        .dz-pagedel { font-size: 12px; border: 1px solid #fecaca; border-radius: 6px; padding: 2px 7px; color: #dc2626; background: #fff; cursor: pointer; }
        .dz-pagedel:hover { background: #fef2f2; }
        .dz-el { position: absolute; overflow: hidden; box-sizing: border-box; cursor: move; border: 1px dashed transparent; line-height: 1.3; padding: 0 1px; }
        .dz-el:hover { border-color: #c7d2fe; }
        .dz-el.dz-sel { border: 1px solid #6366f1; z-index: 5; }
        .dz-el img { width: 100%; height: 100%; object-fit: contain; pointer-events: none; }
        .dz-el b { display: block; }
        .dz-el.dz-tb { line-height: 1.35; }
        .dz-el.dz-tb-of { outline: 2px solid #f59e0b; }
        .dz-tb-badge { position: absolute; z-index: 6; background: #f59e0b; color: #fff; font: 600 10px/1.2 sans-serif; padding: 1px 5px; border-radius: 6px; white-space: nowrap; pointer-events: none; box-shadow: 0 1px 3px rgba(0,0,0,.25); }
        .dz-tb-catch { position: absolute; z-index: 6; background: #6366f1; color: #fff; font: 600 10px/1.2 sans-serif; padding: 1px 5px; border-radius: 6px; white-space: nowrap; pointer-events: none; box-shadow: 0 1px 3px rgba(0,0,0,.25); }
        .dz-sig { border-top: 1px solid #374151; padding-top: 2px; }
        .dz-h { position: absolute; width: 10px; height: 10px; background: #6366f1; border: 1px solid #fff; box-sizing: border-box; }
        .dz-h-e { right: -5px; top: 50%; margin-top: -5px; cursor: ew-resize; }
        .dz-h-s { bottom: -5px; left: 50%; margin-left: -5px; cursor: ns-resize; }
        .dz-h-se { right: -5px; bottom: -5px; cursor: nwse-resize; }
        #dz-app .dz-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; }
        #dz-app .dz-add { display: block; width: 100%; text-align: left; border: 1px solid #e5e7eb; border-radius: 8px; padding: 7px 10px; margin-top: 6px; font-size: 13px; color: #374151; background: #fff; cursor: pointer; }
        #dz-app .dz-add:hover { background: #f9fafb; }
        #dz-props label { display: block; font-size: 12px; color: #6b7280; margin-top: 8px; }
        #dz-props input[type=text], #dz-props input[type=number], #dz-props select { width: 100%; margin-top: 2px; border: 1px solid #d1d5db; border-radius: 6px; padding: 5px 7px; font-size: 13px; }
        #dz-props textarea { width: 100%; margin-top: 2px; border: 1px solid #d1d5db; border-radius: 6px; padding: 5px 7px; font-size: 13px; resize: vertical; font-family: inherit; }
        #dz-props .dz-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        #dz-props .dz-check { display: flex; align-items: center; gap: 6px; margin-top: 10px; font-size: 13px; color: #374151; }
        #dz-props .dz-check input { margin-top: 0; width: auto; }
        #dz-props .dz-fmt { display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap; }
        /* Umschalt-Buttons (F/K/U): leuchten aktiv */
        .dz-toggle { position: relative; display: inline-flex; }
        .dz-toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
        .dz-toggle span { display: inline-flex; align-items: center; justify-content: center; min-width: 38px; height: 34px; padding: 0 10px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; color: #374151; cursor: pointer; font-size: 15px; user-select: none; }
        .dz-toggle span:hover { background: #f3f4f6; }
        .dz-toggle input:checked + span { background: #4f46e5; border-color: #4f46e5; color: #fff; }
        .dz-toggle input:focus-visible + span { outline: 2px solid #a5b4fc; outline-offset: 1px; }
        /* Ein/Aus-Schalter (Auffangfeld) */
        #dz-props label.dz-switch { display: flex; align-items: center; gap: 10px; margin-top: 12px; cursor: pointer; font-size: 13px; color: #374151; }
        .dz-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
        .dz-switch .dz-track { flex: none; width: 40px; height: 22px; border-radius: 999px; background: #d1d5db; position: relative; transition: background .15s; margin-top: 1px; }
        .dz-switch .dz-thumb { position: absolute; top: 2px; left: 2px; width: 18px; height: 18px; border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.3); transition: left .15s; }
        .dz-switch input:checked + .dz-track { background: #4f46e5; }
        .dz-switch input:checked + .dz-track .dz-thumb { left: 20px; }
        .dz-switch input:focus-visible + .dz-track { outline: 2px solid #a5b4fc; outline-offset: 2px; }
        #dz-props input[type=color] { width: 100%; height: 30px; margin-top: 2px; padding: 2px; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; }
        #dz-props .dz-bg { display: flex; align-items: center; gap: 6px; }
        #dz-props .dz-bg input[type=checkbox] { margin-top: 0; width: auto; }
        #dz-props .dz-bg input[type=color] { flex: 1; }
        #dz-props .dz-typ { display: inline-block; font-size: 12px; font-weight: 600; color: #4f46e5; background: #eef2ff; border-radius: 999px; padding: 2px 10px; }
        #dz-props .dz-btn { margin-top: 10px; width: 100%; border: 1px solid #d1d5db; color: #374151; border-radius: 8px; padding: 7px; font-size: 13px; background: #fff; cursor: pointer; }
        #dz-props .dz-btn:hover { background: #f9fafb; }
        #dz-props .dz-del { margin-top: 14px; width: 100%; border: 1px solid #fecaca; color: #dc2626; border-radius: 8px; padding: 7px; font-size: 13px; background: #fff; cursor: pointer; }
        #dz-props .dz-del:hover { background: #fef2f2; }
        #dz-app .dz-hint { font-size: 12px; color: #9ca3af; }
        #dz-varhelp { display: inline-flex; align-items: center; gap: 6px; background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; border-radius: 8px; padding: 8px 12px; font-size: 14px; font-weight: 500; cursor: pointer; }
        #dz-varhelp:hover { background: #fde68a; }
        #dz-modal { display: none; position: fixed; inset: 0; background: rgba(15,23,42,.45); z-index: 60; align-items: center; justify-content: center; padding: 20px; }
        #dz-modal.dz-open { display: flex; }
        #dz-modal .dz-box { background: #fff; border-radius: 14px; max-width: 540px; width: 100%; max-height: 82vh; overflow: auto; padding: 24px; box-shadow: 0 10px 40px rgba(0,0,0,.2); }
        #dz-modal h2 { font-size: 18px; font-weight: 600; color: #1f2937; margin: 0 0 8px; }
        #dz-modal p { font-size: 14px; color: #4b5563; margin: 0 0 14px; line-height: 1.5; }
        #dz-modal table { width: 100%; border-collapse: collapse; font-size: 13px; }
        #dz-modal th, #dz-modal td { text-align: left; padding: 7px 8px; border-bottom: 1px solid #eef2f7; }
        #dz-modal th { color: #6b7280; font-weight: 600; }
        #dz-modal code { background: #eef2ff; color: #4f46e5; padding: 1px 6px; border-radius: 5px; }
        #dz-modal .dz-close { margin-top: 16px; background: #4f46e5; color: #fff; border: none; border-radius: 8px; padding: 8px 16px; font-size: 14px; cursor: pointer; }
        #dz-tp-editor input[type=text], #dz-tp-editor textarea { border: 1px solid #d1d5db; border-radius: 6px; padding: 6px 8px; font-size: 13px; font-family: inherit; }
        #dz-tp-editor textarea { width: 100%; margin-top: 6px; resize: vertical; }
        .dz-tp-item { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; margin-bottom: 10px; }
        .dz-tp-head { display: flex; gap: 8px; align-items: center; }
        .dz-tp-name { flex: 1; }
        .dz-tp-del { border: 1px solid #fecaca; color: #dc2626; background: #fff; border-radius: 6px; padding: 4px 9px; font-size: 18px; line-height: 1; cursor: pointer; }
        .dz-tp-del:hover { background: #fef2f2; }
        .dz-tp-count { font-size: 11px; color: #9ca3af; margin-top: 3px; }
        .dz-tp-btn { border: 1px solid #d1d5db; color: #374151; background: #fff; border-radius: 8px; padding: 7px 12px; font-size: 13px; cursor: pointer; }
        .dz-tp-btn:hover { background: #f9fafb; }
        #dz-tp-actions { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; align-items: center; }
    </style>

    <div id="dz-app">
        <div class="dz-side">
            <div class="dz-card">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Hinzufügen</p>
                <button class="dz-add" data-typ="text">+ Statischer Text</button>
                <button class="dz-add" data-typ="feld">+ Datenfeld</button>
                <button class="dz-add" data-typ="block">+ Textblock</button>
                <button class="dz-add" data-typ="unterschrift">+ Unterschrift</button>
                <button class="dz-add" data-typ="bild">+ Logo / Bild</button>
                <button class="dz-add" data-typ="linie">+ Linie</button>
                <button class="dz-add" data-typ="textbereich">+ Zeugnistext</button>
                <input type="file" id="dz-file" accept="image/*" style="display:none">
                <p class="dz-hint" id="dz-pagehint" style="margin-top:12px;"></p>
                <p class="dz-hint" style="margin-top:8px;">Element anklicken zum Auswählen, ziehen zum Verschieben, an den blauen Griffen die Größe ändern. Danach <strong>Speichern</strong>.</p>
            </div>

            @unless ($format->broschuere)
                <div class="dz-card" style="margin-top:12px;">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Seiten</p>
                    <button class="dz-add" id="dz-addstart" type="button">+ Startseite</button>
                    <button class="dz-add" id="dz-addfolge" type="button">+ Folgeseite</button>
                    <p class="dz-hint" style="margin-top:8px;">Startseiten erscheinen genau einmal. <strong>Folgeseiten wiederholen sich</strong> im fertigen Zeugnis so oft, bis der ganze Zeugnistext ausgegeben ist – bei kurzem Text entfallen sie ganz. Die Schrift wird dabei nie verkleinert.</p>
                </div>
            @endunless
        </div>

        <div class="dz-canvas">
            <div id="dz-pages"></div>
        </div>

        <div class="dz-side">
            <div class="dz-card">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Eigenschaften</p>
                <div id="dz-props"></div>
            </div>
        </div>
    </div>

    <div id="dz-modal">
        <div class="dz-box">
            <h2>Variablen in Textfeldern</h2>
            <p>In einem <strong>„Statischer Text"</strong>-Feld kannst du Platzhalter in geschweiften Klammern verwenden – beim Erstellen des Zeugnisses werden sie automatisch mit den echten Daten des Schülers gefüllt.<br><br>Beispiel:<br><code>erhält für die Klasse {Klasse} im Schuljahr {Schuljahr} folgendes Zeugnis:</code></p>
            <table><thead><tr><th>Variable</th><th>Beispiel-Inhalt</th></tr></thead><tbody id="dz-vartable"></tbody></table>
            <p style="margin-top:16px;"><code>{Zeugnistext}</code> ist ein Sonderfall: Er steht für den kompletten Zeugnistext (Haupttext + Fachtexte) und wird <strong>nicht</strong> in ein Statischer-Text-Feld eingesetzt, sondern über die <strong>Zeugnistext-Felder</strong> ausgegeben. Dort verteilt er sich automatisch der Reihe nach über alle diese Felder – sortiert nach Seite und Position. Passt der Text nicht in alle Felder, wird eine Überlauf-Warnung angezeigt.</p>
            <label style="display:block; margin-top:16px; font-size:14px; font-weight:600; color:#1f2937;">Beispieltext für die Vorschau
                <select id="dz-textprobe" style="display:block; width:100%; margin-top:6px; border:1px solid #d1d5db; border-radius:8px; padding:8px 10px; font-size:14px; background:#fff;"></select>
            </label>
            <p style="margin-top:6px; font-size:12px; color:#9ca3af;">Steuert nur die Layout-Vorschau (Designer und Vorschau/PDF) – ändert das gespeicherte Zeugnis nicht.</p>

            <button id="dz-tp-toggle" class="dz-tp-btn" type="button" style="margin-top:10px;">Beispieltexte bearbeiten …</button>
            <div id="dz-tp-editor" style="display:none; margin-top:12px; border-top:1px solid #eef2f7; padding-top:12px;">
                <div id="dz-tp-list"></div>
                <button id="dz-tp-add" class="dz-tp-btn" type="button">+ Variante hinzufügen</button>
                <div id="dz-tp-actions">
                    <button id="dz-tp-save" class="dz-close" type="button" style="margin-top:0;">Texte speichern</button>
                    <button id="dz-tp-reset" class="dz-tp-btn" type="button">Auf Standard zurücksetzen</button>
                    <span id="dz-tp-status"></span>
                </div>
                <p style="margin-top:8px; font-size:12px; color:#9ca3af;">Gilt modulweit für die Vorschau aller Formate. Die Wörterzahl wird automatisch aus dem Text ermittelt.</p>
            </div>

            <button id="dz-modal-close" class="dz-close" type="button">Verstanden</button>
        </div>
    </div>

    <script>
        const STATE = { elements: @json($elemente), sel: -1, activePage: 1 };
        const BINDUNGEN = @json($bindungen);
        const VARIABLEN = @json($variablen);
        const DATEN = @json($daten);
        const TEXTPROBEN = @json($textproben);
        const PAGE = @json($designSeite);
        const BROSCHUERE = @json((bool) $format->broschuere);
        let ROLLEN = @json($seitenRollen); // je Seite 'start'|'folge' (leer bei Broschüre)
        let PAGES = @json($seitenAnzahl);
        const LABELS = @json($seitenLabels);
        const SCALE = 2.5;
        const SAVE_URL = @json(route('module.schulzeugnis.formate.layout', $format));
        const UPLOAD_URL = @json(route('module.schulzeugnis.formate.bild', $format));
        const TP_SAVE_URL = @json(route('module.schulzeugnis.beispieltexte.save'));
        const TP_RESET_URL = @json(route('module.schulzeugnis.beispieltexte.reset'));
        const BILD_BASE = '/storage/';
        const CSRF = @json(csrf_token());
        const TYPLABEL = { text: 'Statischer Text', feld: 'Datenfeld', block: 'Textblock', unterschrift: 'Unterschrift', bild: 'Logo / Bild', linie: 'Linie', textbereich: 'Zeugnistext' };

        STATE.elements.forEach((e) => { if (!e.seite) e.seite = 1; });

        const pagesWrap = document.getElementById('dz-pages');
        const props = document.getElementById('dz-props');
        const statusEl = document.getElementById('dz-status');
        const pageHint = document.getElementById('dz-pagehint');
        const fileInput = document.getElementById('dz-file');
        const pageDivs = {};
        let fileMode = 'add', fileTarget = -1;

        const esc = (s) => String(s == null ? '' : s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

        // --- Live-Textsplit: verteilt {Zeugnistext} auf die Textbereiche wie das PDF ---
        // Spiegelt FormatController::fuelleTextbereiche() + umbrechen() clientseitig.
        // measureText ersetzt dompdfs Schriftvermessung – nur Näherung, aber zeigt die
        // echte Verteilung schon im Designer statt in jedem Rahmen den vollen Text.
        const MM_TO_PT = 2.83465;
        let SPLIT = {};
        let _mctx;
        const measureCtx = () => (_mctx || (_mctx = document.createElement('canvas').getContext('2d')));
        const cssFamily = (fam) => {
            fam = fam || 'DejaVu Sans';
            const generic = (fam.indexOf('Mono') >= 0) ? 'monospace' : (fam.indexOf('Serif') >= 0) ? 'serif' : 'sans-serif';
            return '"' + fam + '", ' + generic;
        };
        function wrapText(text, breite, fontPt, fam) {
            const ctx = measureCtx();
            ctx.font = fontPt + 'px ' + cssFamily(fam); // pt-Zahl als px: konsistent zur Spaltenbreite
            const zeilen = [];
            String(text).split('\n').forEach((absatz) => {
                if (absatz.trim() === '') { zeilen.push(''); return; }
                let aktuell = '';
                absatz.trim().split(/\s+/).forEach((w) => {
                    const probe = aktuell === '' ? w : aktuell + ' ' + w;
                    if (aktuell === '' || ctx.measureText(probe).width <= breite) {
                        aktuell = probe;
                    } else {
                        zeilen.push(aktuell);
                        aktuell = w;
                    }
                });
                zeilen.push(aktuell);
            });
            return zeilen;
        }
        let FOLGE_INFO = { instanzen: 0 };
        function computeSplit() {
            const map = {};
            FOLGE_INFO = { instanzen: 0 };
            const alle = [];
            STATE.elements.forEach((e, i) => { if (e.typ === 'textbereich') alle.push(i); });
            const text = String(DATEN['zeugnistext'] || '');
            if (!alle.length || text.trim() === '') return map;

            const byPos = (a, b) => {
                const ea = STATE.elements[a], eb = STATE.elements[b];
                return (ea.seite || 1) - (eb.seite || 1) || (ea.y || 0) - (eb.y || 0) || (ea.x || 0) - (eb.x || 0);
            };

            // Mit Folgeseiten: Startseiten zuerst, der Rest fließt in Kopien der
            // Folgeseite(n) – im Designer wird die erste Kopie gezeigt, weitere
            // werden nur hochgerechnet (Spiegel von Support\Textverteilung).
            const folgeIdx = alle.filter((i) => rolleVon(STATE.elements[i].seite || 1) === 'folge');
            if (folgeIdx.length) {
                const startIdx = alle.filter((i) => rolleVon(STATE.elements[i].seite || 1) === 'start');
                const first = STATE.elements[alle.slice().sort(byPos)[0]];
                const minBreite = Math.min.apply(null, alle.map((i) => (+STATE.elements[i].w || 40) * MM_TO_PT - 4));
                const zeilen = wrapText(text, Math.max(10, minBreite), +first.size || 11, first.font || 'DejaVu Sans');
                const maxZ = (el) => Math.max(1, Math.floor(((+el.h || 10) * MM_TO_PT) / ((+el.size || 11) * 1.35)));
                const fill = (order, pos) => {
                    order.forEach((i) => {
                        const part = zeilen.slice(pos, pos + maxZ(STATE.elements[i]));
                        map[i] = { lines: part };
                        pos += part.length;
                    });
                    return pos;
                };

                const festS = startIdx.filter((i) => !STATE.elements[i].nurUeberhang).sort(byPos);
                const bedingtS = startIdx.filter((i) => STATE.elements[i].nurUeberhang);
                startIdx.forEach((i) => { map[i] = { lines: [] }; });
                let pos = fill(festS, 0);
                if (pos < zeilen.length && bedingtS.length) pos = fill(startIdx.slice().sort(byPos), 0);

                const folgeSort = folgeIdx.slice().sort(byPos);
                folgeSort.forEach((i) => { map[i] = { lines: [] }; });
                if (pos < zeilen.length) {
                    const kapazitaet = folgeSort.reduce((s, i) => s + maxZ(STATE.elements[i]), 0);
                    pos = fill(folgeSort, pos);
                    FOLGE_INFO.instanzen = 1;
                    if (pos < zeilen.length && kapazitaet > 0) {
                        FOLGE_INFO.instanzen += Math.ceil((zeilen.length - pos) / kapazitaet);
                    }
                }
                return map;
            }

            const fest = alle.filter((i) => !STATE.elements[i].nurUeberhang).sort(byPos);
            const bedingt = alle.filter((i) => STATE.elements[i].nurUeberhang);

            // Verteilt den Text der Reihe nach über die gegebenen Felder.
            const verteile = (order) => {
                const first = STATE.elements[order[0]];
                const minBreite = Math.min.apply(null, order.map((i) => (+STATE.elements[i].w || 40) * MM_TO_PT - 4));
                const zeilen = wrapText(text, Math.max(10, minBreite), +first.size || 11, first.font || 'DejaVu Sans');
                let pos = 0;
                const m = {};
                order.forEach((i) => {
                    const el = STATE.elements[i];
                    const maxZeilen = Math.max(1, Math.floor(((+el.h || 10) * MM_TO_PT) / ((+el.size || 11) * 1.35)));
                    m[i] = { lines: zeilen.slice(pos, pos + maxZeilen) };
                    pos += m[i].lines.length;
                });
                return { m, rest: zeilen.length - pos, order };
            };

            // Erst nur die festen Felder. Reicht es nicht und es gibt Auffang-/
            // Zusatzfelder (nurUeberhang), alle Felder in natürlicher Reihenfolge
            // benutzen – die Zusatzfelder stehen dann an ihrer echten Position.
            let res;
            if (fest.length) {
                res = verteile(fest);
                if (res.rest > 0 && bedingt.length) {
                    res = verteile(alle.slice().sort(byPos));
                }
            } else {
                res = verteile(alle.slice().sort(byPos));
            }

            alle.forEach((i) => { map[i] = res.m[i] || { lines: [] }; });
            if (res.rest > 0) {
                map[res.order[res.order.length - 1]].rest = res.rest;
            }
            return map;
        }
        const r1 = (n) => Math.round(n * 10) / 10;
        const substVars = (t) => String(t).replace(/\{(\w+)\}/g, (m, k) => (k in VARIABLEN && VARIABLEN[k] in DATEN) ? DATEN[VARIABLEN[k]] : m);
        const rolleVon = (n) => BROSCHUERE ? 'start' : (ROLLEN[n - 1] || 'start');
        const kurzLabel = (n) => BROSCHUERE ? (LABELS[n - 1] || '') : (rolleVon(n) === 'folge' ? 'Folgeseite' : 'Startseite');
        const pageLabel = (n) => (PAGES > 1 ? ('Seite ' + n + ' · ' + kurzLabel(n)) : 'Seite');

        function buildPages() {
            pagesWrap.innerHTML = '';
            for (let n = 1; n <= PAGES; n++) {
                const block = document.createElement('div');
                if (PAGES > 1) {
                    const bar = document.createElement('div');
                    bar.className = 'dz-pagebar';
                    const lab = document.createElement('span');
                    lab.className = 'dz-pagelabel';
                    lab.textContent = BROSCHUERE ? pageLabel(n) : ('Seite ' + n);
                    bar.appendChild(lab);
                    if (!BROSCHUERE) {
                        const rsel = document.createElement('select');
                        rsel.className = 'dz-swap';
                        rsel.title = 'Rolle dieser Seite';
                        rsel.innerHTML = '<option value="start"' + (rolleVon(n) === 'start' ? ' selected' : '') + '>Startseite</option>'
                            + '<option value="folge"' + (rolleVon(n) === 'folge' ? ' selected' : '') + '>Folgeseite</option>';
                        rsel.addEventListener('change', () => {
                            ROLLEN[n - 1] = rsel.value;
                            buildPages(); renderProps(); render();
                            statusEl.textContent = 'Seite ' + n + ' ist jetzt ' + kurzLabel(n) + ' (noch nicht gespeichert)';
                        });
                        bar.appendChild(rsel);
                    }
                    const sel = document.createElement('select');
                    sel.className = 'dz-swap';
                    let opts = '<option value="">Seite tauschen mit …</option>';
                    for (let m = 1; m <= PAGES; m++) if (m !== n) opts += '<option value="' + m + '">Seite ' + m + ' (' + esc(kurzLabel(m)) + ')</option>';
                    sel.innerHTML = opts;
                    sel.addEventListener('change', () => { if (sel.value) { swapPages(n, +sel.value); sel.value = ''; } });
                    bar.appendChild(sel);
                    if (!BROSCHUERE) {
                        const del = document.createElement('button');
                        del.type = 'button';
                        del.className = 'dz-pagedel';
                        del.title = 'Seite löschen';
                        del.textContent = '×';
                        del.addEventListener('click', () => deletePage(n));
                        bar.appendChild(del);
                    }
                    block.appendChild(bar);
                }
                const pg = document.createElement('div');
                pg.className = 'dz-page';
                pg.dataset.seite = n;
                pg.style.width = (PAGE.b * SCALE) + 'px';
                pg.style.height = (PAGE.h * SCALE) + 'px';
                pg.addEventListener('mousedown', (e) => {
                    if (e.target === pg) { STATE.activePage = n; STATE.sel = -1; renderProps(); render(); }
                });
                block.appendChild(pg);
                pagesWrap.appendChild(block);
                pageDivs[n] = pg;
            }
        }

        function displayHtml(el, i) {
            if (el.typ === 'text') return esc(substVars(el.text || '(Text)'));
            if (el.typ === 'unterschrift') return '<div class="dz-sig">' + esc(el.text || DATEN['unterschrift'] || '') + '</div>';
            if (el.typ === 'feld') return esc(el.bindung in DATEN ? DATEN[el.bindung] : '{' + (el.bindung || '') + '}');
            if (el.typ === 'bild') return el.bild ? '<img src="' + BILD_BASE + esc(el.bild) + '">' : '<span style="font-size:11px;color:#9ca3af;">Bild wählen …</span>';
            if (el.typ === 'linie') return '<div style="border-top:' + (el.staerke || 0.3) + 'mm ' + (el.stil || 'solid') + ' #374151;"></div>';
            if (el.typ === 'textbereich') {
                const part = SPLIT[i];
                if (!part) return '<span style="color:#9ca3af;">(Zeugnistext)</span>';
                const inhalt = part.lines.map(esc).join('<br>');
                if (inhalt) return inhalt;
                if (rolleVon(el.seite || 1) === 'folge') return '<span style="color:#cbd5e1;">(Fortsetzung des Zeugnistexts – füllt sich bei Überhang)</span>';
                return '<span style="color:#cbd5e1;">' + (el.nurUeberhang ? '(nur bei Überhang)' : '(leer)') + '</span>';
            }
            if (el.typ === 'block') {
                if (el.bindung === 'fachtexte') {
                    return (DATEN['fachtexte'] || []).map((f) => '<b>' + esc(f.fach) + '</b>' + esc(f.text)).join('');
                }
                return esc(DATEN[el.bindung] || '').replace(/\n/g, '<br>');
            }
            return '';
        }

        function render() {
            SPLIT = computeSplit();
            for (let n = 1; n <= PAGES; n++) {
                pageDivs[n].innerHTML = '';
                pageDivs[n].classList.toggle('dz-active', PAGES > 1 && n === STATE.activePage);
                pageDivs[n].classList.toggle('dz-page-folge', rolleVon(n) === 'folge');
                if (rolleVon(n) === 'folge') {
                    const tag = document.createElement('div');
                    tag.className = 'dz-folgetag';
                    tag.textContent = FOLGE_INFO.instanzen > 1
                        ? ('Folgeseite · im PDF ≈ ' + FOLGE_INFO.instanzen + '× wiederholt')
                        : (FOLGE_INFO.instanzen === 1 ? 'Folgeseite · wird 1× gebraucht' : 'Folgeseite · entfällt bei diesem Text');
                    pageDivs[n].appendChild(tag);
                }
            }
            STATE.elements.forEach((el, i) => {
                const pg = pageDivs[el.seite || 1];
                if (!pg) return;
                const d = document.createElement('div');
                const ueberlauf = el.typ === 'textbereich' && SPLIT[i] && SPLIT[i].rest > 0;
                d.className = 'dz-el' + (el.typ === 'textbereich' ? ' dz-tb' : '') + (ueberlauf ? ' dz-tb-of' : '') + (i === STATE.sel ? ' dz-sel' : '');
                if (ueberlauf) d.title = 'Text läuft über: ' + SPLIT[i].rest + ' Zeile(n) passen nicht mehr in die Textbereiche.';
                d.style.left = (el.x * SCALE) + 'px';
                d.style.top = (el.y * SCALE) + 'px';
                d.style.width = (el.w * SCALE) + 'px';
                d.style.height = (el.h * SCALE) + 'px';
                d.style.fontSize = (el.size * 0.3528 * SCALE) + 'px';
                d.style.textAlign = el.align || 'left';
                d.style.fontWeight = el.bold ? '700' : '400';
                if (['text', 'feld', 'block', 'unterschrift', 'textbereich'].includes(el.typ)) {
                    const fam = el.font || 'DejaVu Sans';
                    const generic = (fam.indexOf('Mono') >= 0) ? 'monospace' : (fam.indexOf('Serif') >= 0) ? 'serif' : 'sans-serif';
                    d.style.fontFamily = '"' + fam + '", ' + generic;
                    d.style.fontStyle = el.italic ? 'italic' : 'normal';
                    d.style.textDecoration = el.underline ? 'underline' : 'none';
                    d.style.color = el.color || '#1f2937';
                    d.style.background = el.bg || 'transparent';
                }
                d.innerHTML = displayHtml(el, i);
                d.addEventListener('mousedown', (e) => startDrag(e, i));
                if (i === STATE.sel) {
                    ['e', 's', 'se'].forEach((dir) => {
                        const h = document.createElement('div');
                        h.className = 'dz-h dz-h-' + dir;
                        h.addEventListener('mousedown', (e) => startResize(e, i, dir));
                        d.appendChild(h);
                    });
                }
                pg.appendChild(d);
                if (el.typ === 'textbereich' && el.nurUeberhang) {
                    const tag = document.createElement('div');
                    tag.className = 'dz-tb-catch';
                    tag.textContent = 'nur bei Überhang';
                    tag.style.left = (el.x * SCALE) + 'px';
                    tag.style.top = Math.max(0, el.y * SCALE - 15) + 'px';
                    pg.appendChild(tag);
                }
                if (ueberlauf) {
                    const badge = document.createElement('div');
                    badge.className = 'dz-tb-badge';
                    badge.textContent = '⚠ ' + SPLIT[i].rest + ' Zeile(n) über';
                    badge.style.left = (el.x * SCALE) + 'px';
                    badge.style.top = ((el.y + el.h) * SCALE + 2) + 'px';
                    pg.appendChild(badge);
                }
            });
            pageHint.textContent = PAGES > 1 ? ('Neue Elemente landen auf: ' + pageLabel(STATE.activePage)) : '';
        }

        function syncProps() {
            const el = STATE.sel >= 0 ? STATE.elements[STATE.sel] : null;
            if (!el) return;
            ['x', 'y', 'w', 'h'].forEach((k) => {
                const n = document.getElementById('dzp-' + k);
                if (n) n.value = r1(el[k]);
            });
        }

        function startDrag(e, i) {
            e.preventDefault();
            if (STATE.sel !== i) select(i);
            const el = STATE.elements[i];
            const sx = e.clientX, sy = e.clientY, ox = el.x, oy = el.y;
            function move(ev) {
                el.x = Math.max(0, ox + (ev.clientX - sx) / SCALE);
                el.y = Math.max(0, oy + (ev.clientY - sy) / SCALE);
                render(); syncProps();
            }
            function up() { document.removeEventListener('mousemove', move); document.removeEventListener('mouseup', up); }
            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', up);
        }

        function startResize(e, i, dir) {
            e.preventDefault(); e.stopPropagation();
            const el = STATE.elements[i];
            const sx = e.clientX, sy = e.clientY, ow = el.w, oh = el.h;
            function move(ev) {
                if (dir === 'e' || dir === 'se') el.w = Math.max(4, ow + (ev.clientX - sx) / SCALE);
                if (dir === 's' || dir === 'se') el.h = Math.max(4, oh + (ev.clientY - sy) / SCALE);
                render(); syncProps();
            }
            function up() { document.removeEventListener('mousemove', move); document.removeEventListener('mouseup', up); }
            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', up);
        }

        function select(i) {
            STATE.sel = i;
            if (i >= 0) STATE.activePage = STATE.elements[i].seite || 1;
            renderProps(); render();
        }

        function swapPages(a, b) {
            STATE.elements.forEach((el) => {
                const s = el.seite || 1;
                if (s === a) el.seite = b;
                else if (s === b) el.seite = a;
            });
            if (!BROSCHUERE) {
                // Die Rolle wandert mit dem Seiteninhalt.
                const t = ROLLEN[a - 1];
                ROLLEN[a - 1] = ROLLEN[b - 1];
                ROLLEN[b - 1] = t;
            }
            STATE.sel = -1;
            STATE.activePage = b;
            buildPages(); renderProps(); render();
            statusEl.textContent = 'Seiten ' + a + ' und ' + b + ' getauscht (noch nicht gespeichert)';
        }

        function addPage(rolle) {
            if (BROSCHUERE) return;
            ROLLEN.push(rolle);
            PAGES = ROLLEN.length;
            STATE.sel = -1;
            STATE.activePage = PAGES;
            buildPages(); renderProps(); render();
            statusEl.textContent = (rolle === 'folge' ? 'Folgeseite' : 'Startseite') + ' hinzugefügt (noch nicht gespeichert)';
        }

        function deletePage(n) {
            if (BROSCHUERE || PAGES <= 1) return;
            const anzahl = STATE.elements.filter((e) => (e.seite || 1) === n).length;
            if (anzahl > 0 && !confirm('Seite ' + n + ' enthält ' + anzahl + ' Element(e). Seite samt Elementen löschen?')) return;
            STATE.elements = STATE.elements.filter((e) => (e.seite || 1) !== n);
            STATE.elements.forEach((e) => { if ((e.seite || 1) > n) e.seite = (e.seite || 1) - 1; });
            ROLLEN.splice(n - 1, 1);
            PAGES = ROLLEN.length;
            STATE.sel = -1;
            STATE.activePage = Math.min(STATE.activePage, PAGES);
            buildPages(); renderProps(); render();
            statusEl.textContent = 'Seite ' + n + ' gelöscht (noch nicht gespeichert)';
        }

        function renderProps() {
            const el = STATE.sel >= 0 ? STATE.elements[STATE.sel] : null;
            if (!el) { props.innerHTML = '<p class="dz-hint">Kein Element ausgewählt.</p>'; return; }
            const isText = el.typ === 'text' || el.typ === 'unterschrift';
            const isBind = el.typ === 'feld' || el.typ === 'block';
            const isBild = el.typ === 'bild';
            const isLinie = el.typ === 'linie';
            const hasFont = isText || isBind || el.typ === 'textbereich';

            let html = '<div style="margin-top:6px;"><span class="dz-typ">' + TYPLABEL[el.typ] + '</span></div>';

            if (PAGES > 1) {
                let opts = '';
                for (let n = 1; n <= PAGES; n++) opts += '<option value="' + n + '"' + ((el.seite || 1) === n ? ' selected' : '') + '>' + esc(pageLabel(n)) + '</option>';
                html += '<label>Seite<select id="dzp-seite">' + opts + '</select></label>';
                let copts = '<option value="">— auf Seite kopieren —</option>';
                for (let n = 1; n <= PAGES; n++) copts += '<option value="' + n + '">Seite ' + n + ' (' + esc(kurzLabel(n)) + ')</option>';
                html += '<label>Kopie auf Seite<select id="dzp-copyseite">' + copts + '</select></label>';
            }
            if (el.typ === 'textbereich') {
                html += '<p class="dz-hint" style="margin-top:10px; line-height:1.45;">Hier fließt automatisch die Variable <strong>{Zeugnistext}</strong> hinein (Haupttext + Fachtexte). Sie wird der Reihe nach über <strong>alle Zeugnistext-Felder</strong> dieses Zeugnisses verteilt – sortiert nach Seite und Position. Passt der Text nicht in alle Felder, erscheint eine Überlauf-Warnung.</p>';
                html += '<label class="dz-switch"><input id="dzp-ueberhang" type="checkbox"' + (el.nurUeberhang ? ' checked' : '') + '><span class="dz-track"><span class="dz-thumb"></span></span><span>Nur bei Überhang benutzen</span></label>';
                html += '<p class="dz-hint" style="margin-top:6px; line-height:1.45;">Zusatzfeld: wird nur zugeschaltet, wenn der Text sonst nicht in die anderen Felder passt – dann an seiner normalen Position (z. B. zuerst). Passt alles, bleibt es leer.</p>';
            }
            if (el.typ === 'text') {
                html += '<label>Text<textarea id="dzp-text" rows="3">' + esc(el.text || '') + '</textarea></label>';
                html += '<p class="dz-hint" style="margin-top:4px;">Variablen: ' + Object.keys(VARIABLEN).map((k) => '{' + k + '}').join(' ') + '</p>';
            } else if (el.typ === 'unterschrift') {
                html += '<label>Text<input id="dzp-text" type="text" value="' + esc(el.text || '') + '"></label>';
            }
            if (isBind) {
                const bindOpts = Object.keys(BINDUNGEN).map((k) => '<option value="' + k + '"' + (el.bindung === k ? ' selected' : '') + '>' + esc(BINDUNGEN[k]) + '</option>').join('');
                html += '<label>Datenfeld<select id="dzp-bindung">' + bindOpts + '</select></label>';
            }
            html += '<div class="dz-grid">' +
                '<label>X (mm)<input id="dzp-x" type="number" step="1" value="' + r1(el.x) + '"></label>' +
                '<label>Y (mm)<input id="dzp-y" type="number" step="1" value="' + r1(el.y) + '"></label>' +
                '<label>Breite<input id="dzp-w" type="number" step="1" value="' + r1(el.w) + '"></label>' +
                (isLinie ? '' : '<label>Höhe<input id="dzp-h" type="number" step="1" value="' + r1(el.h) + '"></label>') +
                '</div>';
            if (isLinie) {
                html += '<label>Stärke (mm)<input id="dzp-staerke" type="number" step="0.1" value="' + (el.staerke || 0.3) + '"></label>';
                const st = el.stil || 'solid';
                html += '<label>Linienstil<select id="dzp-stil">' +
                    '<option value="solid"' + (st === 'solid' ? ' selected' : '') + '>durchgehend</option>' +
                    '<option value="dashed"' + (st === 'dashed' ? ' selected' : '') + '>gestrichelt</option>' +
                    '<option value="dotted"' + (st === 'dotted' ? ' selected' : '') + '>gepunktet</option>' +
                    '</select></label>';
            }
            if (hasFont) {
                const f = el.font || 'DejaVu Sans';
                const isTb = el.typ === 'textbereich';
                html += '<div class="dz-grid">' +
                    '<label>Schrift (pt)<input id="dzp-size" type="number" step="1" value="' + el.size + '"></label>' +
                    '<label>Ausrichtung<select id="dzp-align">' +
                    '<option value="left"' + (el.align === 'left' ? ' selected' : '') + '>links</option>' +
                    '<option value="center"' + (el.align === 'center' ? ' selected' : '') + '>zentriert</option>' +
                    '<option value="right"' + (el.align === 'right' ? ' selected' : '') + '>rechts</option>' +
                    '</select></label>' +
                    '</div>';
                if (isTb) {
                    // Fließtext: F/K/U ergeben je Feld keinen Sinn; Schrift & Ausrichtung
                    // gelten einheitlich für alle Zeugnistext-Felder.
                    html += '<p class="dz-hint" style="margin-top:4px;">Schrift &amp; Ausrichtung gelten für <strong>alle</strong> Zeugnistext-Felder.</p>';
                } else {
                    html += '<div class="dz-fmt">' +
                        '<label class="dz-toggle" title="Fett"><input id="dzp-bold" type="checkbox"' + (el.bold ? ' checked' : '') + '><span><b>F</b></span></label>' +
                        '<label class="dz-toggle" title="Kursiv"><input id="dzp-italic" type="checkbox"' + (el.italic ? ' checked' : '') + '><span><i>K</i></span></label>' +
                        '<label class="dz-toggle" title="Unterstrichen"><input id="dzp-underline" type="checkbox"' + (el.underline ? ' checked' : '') + '><span><u>U</u></span></label>' +
                        '</div>';
                }
                html += '<label>Schriftart<select id="dzp-font">' +
                    '<option value="DejaVu Sans"' + (f === 'DejaVu Sans' ? ' selected' : '') + '>Standard (Sans)</option>' +
                    '<option value="DejaVu Serif"' + (f === 'DejaVu Serif' ? ' selected' : '') + '>Serif</option>' +
                    '<option value="DejaVu Sans Mono"' + (f === 'DejaVu Sans Mono' ? ' selected' : '') + '>Monospace</option>' +
                    '</select></label>' +
                    '<div class="dz-grid">' +
                    '<label>Textfarbe<input id="dzp-color" type="color" value="' + (el.color || '#1f2937') + '"></label>' +
                    '<label>Hintergrund<span class="dz-bg"><input id="dzp-bgon" type="checkbox"' + (el.bg ? ' checked' : '') + '><input id="dzp-bg" type="color" value="' + (el.bg || '#fff59d') + '"></span></label>' +
                    '</div>';
            }
            if (isBild) html += '<button id="dzp-bildreplace" class="dz-btn" type="button">Bild ersetzen …</button>';
            html += '<button id="dzp-del" class="dz-del" type="button">Element löschen</button>';
            props.innerHTML = html;

            const on = (id, ev, fn) => { const n = document.getElementById(id); if (n) n.addEventListener(ev, fn); };
            on('dzp-seite', 'change', (e) => { el.seite = +e.target.value; STATE.activePage = el.seite; render(); });
            on('dzp-copyseite', 'change', (e) => {
                const ziel = +e.target.value;
                if (!ziel) return;
                const kopie = JSON.parse(JSON.stringify(el));
                kopie.seite = ziel;
                STATE.elements.push(kopie);
                STATE.activePage = ziel;
                select(STATE.elements.length - 1);
            });
            on('dzp-text', 'input', (e) => { el.text = e.target.value; render(); });
            on('dzp-bindung', 'change', (e) => { el.bindung = e.target.value; render(); });
            on('dzp-x', 'input', (e) => { el.x = Math.max(0, +e.target.value || 0); render(); });
            on('dzp-y', 'input', (e) => { el.y = Math.max(0, +e.target.value || 0); render(); });
            on('dzp-w', 'input', (e) => { el.w = Math.max(4, +e.target.value || 4); render(); });
            on('dzp-h', 'input', (e) => { el.h = Math.max(4, +e.target.value || 4); render(); });
            on('dzp-staerke', 'input', (e) => { el.staerke = Math.max(0.1, +e.target.value || 0.3); render(); });
            on('dzp-stil', 'change', (e) => { el.stil = e.target.value; render(); });
            on('dzp-size', 'input', (e) => {
                const v = Math.max(5, +e.target.value || 11);
                if (el.typ === 'textbereich') STATE.elements.forEach((x) => { if (x.typ === 'textbereich') x.size = v; });
                else el.size = v;
                render();
            });
            on('dzp-align', 'change', (e) => {
                const v = e.target.value;
                if (el.typ === 'textbereich') STATE.elements.forEach((x) => { if (x.typ === 'textbereich') x.align = v; });
                else el.align = v;
                render();
            });
            on('dzp-bold', 'change', (e) => { el.bold = e.target.checked; render(); });
            on('dzp-italic', 'change', (e) => { el.italic = e.target.checked; render(); });
            on('dzp-underline', 'change', (e) => { el.underline = e.target.checked; render(); });
            on('dzp-font', 'change', (e) => { el.font = e.target.value; render(); });
            on('dzp-color', 'input', (e) => { el.color = e.target.value; render(); });
            on('dzp-ueberhang', 'change', (e) => { el.nurUeberhang = e.target.checked; render(); });
            on('dzp-bgon', 'change', (e) => { el.bg = e.target.checked ? document.getElementById('dzp-bg').value : null; render(); });
            on('dzp-bg', 'input', (e) => { if (document.getElementById('dzp-bgon').checked) { el.bg = e.target.value; render(); } });
            on('dzp-bildreplace', 'click', () => { fileMode = 'replace'; fileTarget = STATE.sel; fileInput.value = ''; fileInput.click(); });
            on('dzp-del', 'click', () => { STATE.elements.splice(STATE.sel, 1); STATE.sel = -1; renderProps(); render(); });
        }

        function add(typ) {
            if (typ === 'bild') { fileMode = 'add'; fileInput.value = ''; fileInput.click(); return; }
            const el = { typ, seite: STATE.activePage, x: 20, y: 20, w: 80, h: 10, size: 12, align: 'left', bold: false };
            if (typ === 'text') el.text = 'Neuer Text';
            if (typ === 'feld') el.bindung = 'schueler.name';
            if (typ === 'block') { el.bindung = 'haupttext'; el.w = 150; el.h = 60; el.size = 11; }
            if (typ === 'unterschrift') { el.bindung = 'unterschrift'; el.text = 'Klassenlehrer/in'; el.align = 'center'; el.h = 8; el.size = 10; }
            if (typ === 'linie') { el.w = 100; el.h = 2; el.staerke = 0.3; el.stil = 'solid'; }
            if (typ === 'textbereich') { el.w = 160; el.h = 120; el.size = 11; }
            STATE.elements.push(el);
            select(STATE.elements.length - 1);
        }

        // Zielwerte fürs Verkleinern vor dem Upload: unter nginx-Default (1 MB) bleiben
        // und riesige Fotos auf eine sinnvolle Kantenlänge bringen (spart auch PDF-Größe,
        // da das Bild beim Rendern als base64 eingebettet wird).
        const BILD_MAX_KANTE = 1600;            // px, längste Seite
        const BILD_ZIEL_BYTES = 900 * 1024;     // ~0,9 MB

        function ladeBild(file) {
            return new Promise((resolve, reject) => {
                const url = URL.createObjectURL(file);
                const img = new Image();
                img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
                img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Bild nicht lesbar')); };
                img.src = url;
            });
        }

        function canvasBlob(canvas, typ, q) {
            return new Promise((resolve) => canvas.toBlob((b) => resolve(b), typ, q));
        }

        // Gibt eine (ggf. verkleinerte) Datei zurück; im Zweifel das Original.
        async function verkleinereBild(file) {
            if (file.size <= BILD_ZIEL_BYTES) return file;
            let img;
            try { img = await ladeBild(file); } catch (e) { return file; }
            let w = img.naturalWidth || img.width, h = img.naturalHeight || img.height;
            if (!w || !h) return file;

            const scale = Math.min(1, BILD_MAX_KANTE / Math.max(w, h));
            w = Math.max(1, Math.round(w * scale));
            h = Math.max(1, Math.round(h * scale));
            const canvas = document.createElement('canvas');
            canvas.width = w; canvas.height = h;
            const ctx = canvas.getContext('2d');

            // PNG zuerst versuchen (erhält Transparenz), wenn es klein genug wird.
            if (/png$/i.test(file.type)) {
                ctx.clearRect(0, 0, w, h);
                ctx.drawImage(img, 0, 0, w, h);
                const png = await canvasBlob(canvas, 'image/png');
                if (png && png.size <= BILD_ZIEL_BYTES) return new File([png], 'logo.png', { type: 'image/png' });
            }

            // Sonst JPEG auf weißem Grund (wie die PDF-Ausgabe PNG-Alpha weiß füllt).
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, w, h);
            ctx.drawImage(img, 0, 0, w, h);
            let q = 0.85, blob = await canvasBlob(canvas, 'image/jpeg', q);
            while (blob && blob.size > BILD_ZIEL_BYTES && q > 0.5) {
                q -= 0.1;
                blob = await canvasBlob(canvas, 'image/jpeg', q);
            }
            return blob ? new File([blob], 'logo.jpg', { type: 'image/jpeg' }) : file;
        }

        async function onFile() {
            const f = fileInput.files[0];
            if (!f) return;
            statusEl.textContent = 'verarbeite Bild …';
            let datei = f;
            try { datei = await verkleinereBild(f); } catch (e) { datei = f; }
            if (datei !== f) {
                statusEl.textContent = 'Bild verkleinert (' + Math.round(f.size / 1024) + ' → ' + Math.round(datei.size / 1024) + ' KB)';
            }
            const fd = new FormData();
            fd.append('bild', datei);
            statusEl.textContent = 'lade Bild hoch …';
            try {
                const r = await fetch(UPLOAD_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body: fd });
                const ct = r.headers.get('content-type') || '';
                if (!r.ok || !ct.includes('application/json')) { statusEl.textContent = 'Upload fehlgeschlagen – bitte eine gültige Bilddatei wählen'; return; }
                const j = await r.json();
                if (fileMode === 'replace' && fileTarget >= 0) {
                    STATE.elements[fileTarget].bild = j.path; render(); statusEl.textContent = 'Bild ersetzt';
                } else {
                    STATE.elements.push({ typ: 'bild', seite: STATE.activePage, bild: j.path, x: 20, y: 20, w: 40, h: 25, size: 12, align: 'left', bold: false });
                    select(STATE.elements.length - 1); statusEl.textContent = 'Bild hinzugefügt';
                }
            } catch (e) { statusEl.textContent = 'Netzwerkfehler beim Upload'; }
            finally { fileMode = 'add'; fileTarget = -1; fileInput.value = ''; }
        }

        async function save() {
            statusEl.textContent = 'speichere …';
            try {
                const r = await fetch(SAVE_URL, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ elemente: STATE.elements, seiten: BROSCHUERE ? undefined : ROLLEN }),
                });
                if (r.ok) { const j = await r.json(); statusEl.textContent = 'gespeichert (' + j.anzahl + ' Elemente)'; }
                else { statusEl.textContent = 'Fehler beim Speichern (' + r.status + ')'; }
            } catch (err) { statusEl.textContent = 'Netzwerkfehler'; }
        }

        document.querySelectorAll('.dz-add[data-typ]').forEach((b) => b.addEventListener('click', () => add(b.dataset.typ)));
        document.getElementById('dz-save').addEventListener('click', save);
        fileInput.addEventListener('change', onFile);
        const addStartBtn = document.getElementById('dz-addstart');
        if (addStartBtn) addStartBtn.addEventListener('click', () => addPage('start'));
        const addFolgeBtn = document.getElementById('dz-addfolge');
        if (addFolgeBtn) addFolgeBtn.addEventListener('click', () => addPage('folge'));

        document.getElementById('dz-varhelp').addEventListener('click', () => {
            document.getElementById('dz-vartable').innerHTML = Object.keys(VARIABLEN).map((k) => '<tr><td><code>{' + k + '}</code></td><td>' + esc(DATEN[VARIABLEN[k]] || '') + '</td></tr>').join('');
            document.getElementById('dz-modal').classList.add('dz-open');
        });
        document.getElementById('dz-modal-close').addEventListener('click', () => document.getElementById('dz-modal').classList.remove('dz-open'));
        document.getElementById('dz-modal').addEventListener('click', (e) => { if (e.target.id === 'dz-modal') e.currentTarget.classList.remove('dz-open'); });

        // --- Beispieltext-Variante für die Vorschau (kurz / mittel / lang) ---
        const LS_PROBE = 'schulzeugnis.textprobe';
        const vorschauLink = document.getElementById('dz-vorschau');
        const textprobeSel = document.getElementById('dz-textprobe');
        let aktProbe = '1';
        try { const s = localStorage.getItem(LS_PROBE); if (s && TEXTPROBEN[s]) aktProbe = s; } catch (e) {}

        function fuelleTextprobeSelect() {
            textprobeSel.innerHTML = Object.keys(TEXTPROBEN).map((k) =>
                '<option value="' + k + '"' + (k === aktProbe ? ' selected' : '') + '>' + esc(TEXTPROBEN[k].label) + '</option>').join('');
        }
        function setzeVorschauLink() {
            if (!vorschauLink) return;
            const base = vorschauLink.dataset.base || (vorschauLink.dataset.base = vorschauLink.getAttribute('href'));
            vorschauLink.setAttribute('href', base + (base.indexOf('?') >= 0 ? '&' : '?') + 'probe=' + aktProbe);
        }
        function wechsleTextprobe(k) {
            if (!TEXTPROBEN[k]) return;
            aktProbe = k;
            DATEN['zeugnistext'] = TEXTPROBEN[k].text;
            try { localStorage.setItem(LS_PROBE, k); } catch (e) {}
            setzeVorschauLink();
            render();
        }
        if (TEXTPROBEN[aktProbe]) DATEN['zeugnistext'] = TEXTPROBEN[aktProbe].text;
        fuelleTextprobeSelect();
        setzeVorschauLink();
        textprobeSel.addEventListener('change', (e) => wechsleTextprobe(e.target.value));

        // --- Editor für eigene Beispieltexte (modulweit, in der DB gespeichert) ---
        const woerterJs = (t) => (String(t).trim().match(/\S+/g) || []).length;
        const tpToggle = document.getElementById('dz-tp-toggle');
        const tpEditor = document.getElementById('dz-tp-editor');
        const tpList = document.getElementById('dz-tp-list');
        const tpStatus = document.getElementById('dz-tp-status');
        let tpEdit = [];

        function tpEditFromProben() {
            tpEdit = Object.keys(TEXTPROBEN).map((k) => ({ name: TEXTPROBEN[k].name, text: TEXTPROBEN[k].text }));
        }
        function renderTpEditor() {
            tpList.innerHTML = '';
            tpEdit.forEach((v, idx) => {
                const item = document.createElement('div');
                item.className = 'dz-tp-item';
                const head = document.createElement('div');
                head.className = 'dz-tp-head';
                const name = document.createElement('input');
                name.type = 'text'; name.className = 'dz-tp-name'; name.value = v.name; name.placeholder = 'Name der Variante';
                const del = document.createElement('button');
                del.type = 'button'; del.className = 'dz-tp-del'; del.title = 'Variante löschen'; del.innerHTML = '<i class="bx bx-trash"></i>';
                head.appendChild(name); head.appendChild(del);
                const ta = document.createElement('textarea');
                ta.rows = 5; ta.className = 'dz-tp-text'; ta.value = v.text; ta.placeholder = 'Zeugnistext …';
                const count = document.createElement('div');
                count.className = 'dz-tp-count'; count.textContent = woerterJs(v.text) + ' Wörter';
                name.addEventListener('input', (e) => { v.name = e.target.value; });
                ta.addEventListener('input', (e) => { v.text = e.target.value; count.textContent = woerterJs(v.text) + ' Wörter'; });
                del.addEventListener('click', () => { tpEdit.splice(idx, 1); renderTpEditor(); });
                item.appendChild(head); item.appendChild(ta); item.appendChild(count);
                tpList.appendChild(item);
            });
        }
        function replaceTextproben(neu) {
            Object.keys(TEXTPROBEN).forEach((k) => delete TEXTPROBEN[k]);
            Object.assign(TEXTPROBEN, neu || {});
            if (!TEXTPROBEN[aktProbe]) aktProbe = Object.keys(TEXTPROBEN)[0] || '1';
            if (TEXTPROBEN[aktProbe]) DATEN['zeugnistext'] = TEXTPROBEN[aktProbe].text;
            fuelleTextprobeSelect();
            setzeVorschauLink();
            render();
        }
        async function tpSenden(url, method, body) {
            const opt = { method, headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } };
            if (body) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
            const res = await fetch(url, opt);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        }
        tpToggle.addEventListener('click', () => {
            const open = tpEditor.style.display === 'none';
            if (open) { tpEditFromProben(); renderTpEditor(); }
            tpEditor.style.display = open ? 'block' : 'none';
            tpToggle.textContent = open ? 'Editor schließen' : 'Beispieltexte bearbeiten …';
        });
        document.getElementById('dz-tp-add').addEventListener('click', () => {
            tpEdit.push({ name: 'Variante ' + (tpEdit.length + 1), text: '' });
            renderTpEditor();
        });
        document.getElementById('dz-tp-save').addEventListener('click', async () => {
            const texte = tpEdit
                .map((v) => ({ name: (v.name || '').trim(), text: v.text || '' }))
                .filter((v) => v.name !== '' && v.text.trim() !== '');
            if (!texte.length) { tpStatus.textContent = 'Bitte mind. eine Variante mit Name und Text.'; return; }
            tpStatus.textContent = 'Speichere …';
            try {
                const data = await tpSenden(TP_SAVE_URL, 'PUT', { texte });
                replaceTextproben(data.textproben);
                tpEditFromProben(); renderTpEditor();
                tpStatus.textContent = 'Gespeichert ✓';
            } catch (e) { tpStatus.textContent = 'Fehler beim Speichern.'; }
        });
        document.getElementById('dz-tp-reset').addEventListener('click', async () => {
            tpStatus.textContent = 'Setze zurück …';
            try {
                const data = await tpSenden(TP_RESET_URL, 'DELETE', null);
                replaceTextproben(data.textproben);
                tpEditFromProben(); renderTpEditor();
                tpStatus.textContent = 'Auf Standard zurückgesetzt ✓';
            } catch (e) { tpStatus.textContent = 'Fehler beim Zurücksetzen.'; }
        });

        buildPages();
        render();
        renderProps();
    </script>
</x-app-layout>
