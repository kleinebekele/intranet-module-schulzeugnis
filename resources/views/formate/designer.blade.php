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
                <a href="{{ route('module.schulzeugnis.formate.vorschau', $format) }}" target="_blank"
                   class="rounded-lg border border-indigo-200 px-3 py-2 text-sm text-indigo-600 hover:bg-indigo-50">Vorschau</a>
                <a href="{{ route('module.schulzeugnis.formate.index') }}"
                   class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">Fertig</a>
                <button id="dz-save" type="button"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Speichern</button>
            </div>
        </div>
    </x-slot>

    <style>
        #dz-app { display: flex; gap: 16px; align-items: flex-start; }
        #dz-app .dz-side { width: 220px; flex: none; }
        #dz-app .dz-canvas { flex: 1; overflow: auto; max-height: 78vh; background: #f3f4f6; border-radius: 12px; padding: 24px; }
        #dz-pages { display: flex; flex-direction: column; gap: 22px; align-items: center; }
        .dz-pagelabel { font-size: 12px; color: #6b7280; margin-bottom: 6px; text-align: center; }
        .dz-page { position: relative; background: #fff; box-shadow: 0 1px 8px rgba(0,0,0,.15); }
        .dz-page.dz-active { outline: 2px solid #a5b4fc; }
        .dz-el { position: absolute; overflow: hidden; box-sizing: border-box; cursor: move; border: 1px dashed transparent; line-height: 1.3; padding: 0 1px; }
        .dz-el:hover { border-color: #c7d2fe; }
        .dz-el.dz-sel { border: 1px solid #6366f1; z-index: 5; }
        .dz-el b { display: block; }
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
        #dz-props .dz-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        #dz-props .dz-check { display: flex; align-items: center; gap: 6px; margin-top: 10px; font-size: 13px; color: #374151; }
        #dz-props .dz-check input { margin-top: 0; width: auto; }
        #dz-props .dz-typ { display: inline-block; font-size: 12px; font-weight: 600; color: #4f46e5; background: #eef2ff; border-radius: 999px; padding: 2px 10px; }
        #dz-props .dz-del { margin-top: 14px; width: 100%; border: 1px solid #fecaca; color: #dc2626; border-radius: 8px; padding: 7px; font-size: 13px; background: #fff; cursor: pointer; }
        #dz-props .dz-del:hover { background: #fef2f2; }
        #dz-app .dz-hint { font-size: 12px; color: #9ca3af; }
    </style>

    <div id="dz-app">
        <div class="dz-side">
            <div class="dz-card">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Hinzufügen</p>
                <button class="dz-add" data-typ="text">+ Statischer Text</button>
                <button class="dz-add" data-typ="feld">+ Datenfeld</button>
                <button class="dz-add" data-typ="block">+ Textblock</button>
                <button class="dz-add" data-typ="unterschrift">+ Unterschrift</button>
                <p class="dz-hint" id="dz-pagehint" style="margin-top:12px;"></p>
                <p class="dz-hint" style="margin-top:8px;">Element anklicken zum Auswählen, ziehen zum Verschieben, an den blauen Griffen die Größe ändern. Danach <strong>Speichern</strong>.</p>
            </div>
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

    <script>
        const STATE = { elements: @json($elemente), sel: -1, activePage: 1 };
        const BINDUNGEN = @json($bindungen);
        const DATEN = @json($daten);
        const PAGE = @json($designSeite);
        const PAGES = @json($seitenAnzahl);
        const LABELS = @json($seitenLabels);
        const SCALE = 2.5;
        const SAVE_URL = @json(route('module.schulzeugnis.formate.layout', $format));
        const CSRF = @json(csrf_token());
        const TYPLABEL = { text: 'Statischer Text', feld: 'Datenfeld', block: 'Textblock', unterschrift: 'Unterschrift' };

        STATE.elements.forEach((e) => { if (!e.seite) e.seite = 1; });

        const pagesWrap = document.getElementById('dz-pages');
        const props = document.getElementById('dz-props');
        const statusEl = document.getElementById('dz-status');
        const pageHint = document.getElementById('dz-pagehint');
        const pageDivs = {};

        const esc = (s) => String(s == null ? '' : s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
        const r1 = (n) => Math.round(n * 10) / 10;
        const pageLabel = (n) => (PAGES > 1 ? ('Seite ' + n + ' · ' + (LABELS[n - 1] || '')) : 'Seite');

        function buildPages() {
            pagesWrap.innerHTML = '';
            for (let n = 1; n <= PAGES; n++) {
                const block = document.createElement('div');
                if (PAGES > 1) {
                    const lab = document.createElement('div');
                    lab.className = 'dz-pagelabel';
                    lab.textContent = pageLabel(n);
                    block.appendChild(lab);
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

        function displayHtml(el) {
            if (el.typ === 'text') return esc(el.text || '(Text)');
            if (el.typ === 'unterschrift') return '<div class="dz-sig">' + esc(el.text || DATEN['unterschrift'] || '') + '</div>';
            if (el.typ === 'feld') return esc(el.bindung in DATEN ? DATEN[el.bindung] : '{' + (el.bindung || '') + '}');
            if (el.typ === 'block') {
                if (el.bindung === 'fachtexte') {
                    return (DATEN['fachtexte'] || []).map((f) => '<b>' + esc(f.fach) + '</b>' + esc(f.text)).join('');
                }
                return esc(DATEN[el.bindung] || '').replace(/\n/g, '<br>');
            }
            return '';
        }

        function render() {
            for (let n = 1; n <= PAGES; n++) {
                pageDivs[n].innerHTML = '';
                pageDivs[n].classList.toggle('dz-active', PAGES > 1 && n === STATE.activePage);
            }
            STATE.elements.forEach((el, i) => {
                const pg = pageDivs[el.seite || 1];
                if (!pg) return;
                const d = document.createElement('div');
                d.className = 'dz-el' + (i === STATE.sel ? ' dz-sel' : '');
                d.style.left = (el.x * SCALE) + 'px';
                d.style.top = (el.y * SCALE) + 'px';
                d.style.width = (el.w * SCALE) + 'px';
                d.style.height = (el.h * SCALE) + 'px';
                d.style.fontSize = (el.size * 0.3528 * SCALE) + 'px';
                d.style.textAlign = el.align || 'left';
                d.style.fontWeight = el.bold ? '700' : '400';
                d.innerHTML = displayHtml(el);
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

        function renderProps() {
            const el = STATE.sel >= 0 ? STATE.elements[STATE.sel] : null;
            if (!el) { props.innerHTML = '<p class="dz-hint">Kein Element ausgewählt.</p>'; return; }
            const isText = el.typ === 'text' || el.typ === 'unterschrift';
            const isBind = el.typ === 'feld' || el.typ === 'block';
            const bindOpts = Object.keys(BINDUNGEN).map((k) => '<option value="' + k + '"' + (el.bindung === k ? ' selected' : '') + '>' + esc(BINDUNGEN[k]) + '</option>').join('');
            let seiteFeld = '';
            if (PAGES > 1) {
                let opts = '';
                for (let n = 1; n <= PAGES; n++) opts += '<option value="' + n + '"' + ((el.seite || 1) === n ? ' selected' : '') + '>' + esc(pageLabel(n)) + '</option>';
                seiteFeld = '<label>Seite<select id="dzp-seite">' + opts + '</select></label>';
            }
            props.innerHTML =
                '<div style="margin-top:6px;"><span class="dz-typ">' + TYPLABEL[el.typ] + '</span></div>' +
                seiteFeld +
                (isText ? '<label>Text<input id="dzp-text" type="text" value="' + esc(el.text || '') + '"></label>' : '') +
                (isBind ? '<label>Datenfeld<select id="dzp-bindung">' + bindOpts + '</select></label>' : '') +
                '<div class="dz-grid">' +
                '<label>X (mm)<input id="dzp-x" type="number" step="1" value="' + r1(el.x) + '"></label>' +
                '<label>Y (mm)<input id="dzp-y" type="number" step="1" value="' + r1(el.y) + '"></label>' +
                '<label>Breite<input id="dzp-w" type="number" step="1" value="' + r1(el.w) + '"></label>' +
                '<label>Höhe<input id="dzp-h" type="number" step="1" value="' + r1(el.h) + '"></label>' +
                '</div>' +
                '<div class="dz-grid">' +
                '<label>Schrift (pt)<input id="dzp-size" type="number" step="1" value="' + el.size + '"></label>' +
                '<label>Ausrichtung<select id="dzp-align">' +
                '<option value="left"' + (el.align === 'left' ? ' selected' : '') + '>links</option>' +
                '<option value="center"' + (el.align === 'center' ? ' selected' : '') + '>zentriert</option>' +
                '<option value="right"' + (el.align === 'right' ? ' selected' : '') + '>rechts</option>' +
                '</select></label>' +
                '</div>' +
                '<label class="dz-check"><input id="dzp-bold" type="checkbox"' + (el.bold ? ' checked' : '') + '> Fett</label>' +
                '<button id="dzp-del" class="dz-del" type="button">Element löschen</button>';

            const on = (id, ev, fn) => { const n = document.getElementById(id); if (n) n.addEventListener(ev, fn); };
            on('dzp-seite', 'change', (e) => { el.seite = +e.target.value; STATE.activePage = el.seite; render(); });
            on('dzp-text', 'input', (e) => { el.text = e.target.value; render(); });
            on('dzp-bindung', 'change', (e) => { el.bindung = e.target.value; render(); });
            on('dzp-x', 'input', (e) => { el.x = Math.max(0, +e.target.value || 0); render(); });
            on('dzp-y', 'input', (e) => { el.y = Math.max(0, +e.target.value || 0); render(); });
            on('dzp-w', 'input', (e) => { el.w = Math.max(4, +e.target.value || 4); render(); });
            on('dzp-h', 'input', (e) => { el.h = Math.max(4, +e.target.value || 4); render(); });
            on('dzp-size', 'input', (e) => { el.size = Math.max(5, +e.target.value || 11); render(); });
            on('dzp-align', 'change', (e) => { el.align = e.target.value; render(); });
            on('dzp-bold', 'change', (e) => { el.bold = e.target.checked; render(); });
            on('dzp-del', 'click', () => { STATE.elements.splice(STATE.sel, 1); STATE.sel = -1; renderProps(); render(); });
        }

        function add(typ) {
            const el = { typ, seite: STATE.activePage, x: 20, y: 20, w: 80, h: 10, size: 12, align: 'left', bold: false };
            if (typ === 'text') el.text = 'Neuer Text';
            if (typ === 'feld') el.bindung = 'schueler.name';
            if (typ === 'block') { el.bindung = 'haupttext'; el.w = 150; el.h = 60; el.size = 11; }
            if (typ === 'unterschrift') { el.bindung = 'unterschrift'; el.text = 'Klassenlehrer/in'; el.align = 'center'; el.h = 8; el.size = 10; }
            STATE.elements.push(el);
            select(STATE.elements.length - 1);
        }

        async function save() {
            statusEl.textContent = 'speichere …';
            try {
                const r = await fetch(SAVE_URL, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ elemente: STATE.elements }),
                });
                if (r.ok) { const j = await r.json(); statusEl.textContent = 'gespeichert (' + j.anzahl + ' Elemente)'; }
                else { statusEl.textContent = 'Fehler beim Speichern (' + r.status + ')'; }
            } catch (err) { statusEl.textContent = 'Netzwerkfehler'; }
        }

        document.querySelectorAll('.dz-add').forEach((b) => b.addEventListener('click', () => add(b.dataset.typ)));
        document.getElementById('dz-save').addEventListener('click', save);

        buildPages();
        render();
        renderProps();
    </script>
</x-app-layout>
