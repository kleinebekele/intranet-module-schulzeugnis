{{-- Gemeinsame Editor-Interaktion: Text-Tabs (falls vorhanden), „Danach weiter",
     Status-Dropdown, Korrektoren-Auswahl und Vergleichs-Modal. Alle Blöcke sind
     no-ops, wenn das jeweilige Element fehlt – so nutzbar für Abschnitt (mit Tabs)
     UND Klassentext (ein Textfeld). --}}
<script>
    // Text-Tabs: Schülertext | Klassenweiter Text. Beide Textareas bleiben im
    // Formular (werden gespeichert) – hier wechselt nur die Sichtbarkeit.
    (function () {
        const root = document.getElementById('zt-txt');
        if (!root) return;
        const tabs = [...root.querySelectorAll('.zt-txt-tab')];
        const panels = [...root.querySelectorAll('.zt-txt-panel')];
        function zeige(name) {
            tabs.forEach((t) => t.classList.toggle('aktiv', t.dataset.txt === name));
            panels.forEach((p) => { p.hidden = p.dataset.txt !== name; });
        }
        tabs.forEach((t) => t.addEventListener('click', () => zeige(t.dataset.txt)));
        if (tabs.length) { zeige(tabs[0].dataset.txt); }
    })();

    (function () {
        const nav = document.getElementById('zt-nav');
        if (!nav) return;
        const KEY = 'zt-weiter';
        const radios = [...nav.querySelectorAll('input[name="weiter"]')];

        // Zuletzt gewähltes Ziel übernehmen (falls verfügbar) – erleichtert das
        // Durchgehen einer Klasse in eine Richtung.
        const stored = localStorage.getItem(KEY);
        if (stored) {
            const r = radios.find((x) => x.value === stored && !x.disabled);
            if (r) r.checked = true;
        }
        radios.forEach((r) => r.addEventListener('change', () => {
            if (r.checked) localStorage.setItem(KEY, r.value);
        }));

        // Abbrechen = ohne Speichern zum gewählten Ziel wechseln.
        const cancel = document.getElementById('zt-cancel');
        if (cancel) cancel.addEventListener('click', () => {
            const sel = radios.find((x) => x.checked);
            if (sel && sel.dataset.url) window.location.assign(sel.dataset.url);
        });
    })();

    // Eigenes Status-Dropdown (Icon + Farbe wie in der Übersicht).
    (function () {
        const root = document.getElementById('zt-status');
        if (!root || root.classList.contains('zt-status-ro')) return;
        const btn = root.querySelector('.zt-status-btn');
        const list = root.querySelector('.zt-status-list');
        const hidden = root.querySelector('input[type=hidden]');
        const label = root.querySelector('.zt-status-label');
        const icon = btn.querySelector('.bx');

        btn.addEventListener('click', (e) => { e.stopPropagation(); list.hidden = !list.hidden; });
        list.querySelectorAll('li').forEach((li) => {
            li.addEventListener('click', () => {
                hidden.value = li.dataset.value;
                label.textContent = li.dataset.label;
                icon.className = 'bx ' + li.dataset.icon;
                icon.style.color = li.dataset.color;
                list.hidden = true;
            });
        });
        document.addEventListener('click', (e) => { if (!root.contains(e.target)) list.hidden = true; });
    })();

    // Korrektoren – durchsuchbare Mehrfachauswahl mit Chips.
    (function () {
        const root = document.getElementById('zt-korr');
        if (!root) return;
        const box = root.querySelector('.zt-korr-box');
        const input = root.querySelector('.zt-korr-input');
        const list = root.querySelector('.zt-korr-list');
        const alle = JSON.parse(document.getElementById('zt-korr-data').textContent);
        const readonly = root.classList.contains('zt-korr-ro');
        let selected = JSON.parse(document.getElementById('zt-korr-selected').textContent).map(Number);

        const nameOf = (id) => { const t = alle.find((a) => a.id === id); return t ? t.name : ''; };

        function renderChips() {
            box.querySelectorAll('.zt-korr-chip, input[type=hidden]').forEach((e) => e.remove());
            selected.forEach((id) => {
                const chip = document.createElement('span');
                chip.className = 'zt-korr-chip';
                const nm = document.createElement('span');
                nm.textContent = nameOf(id);
                chip.appendChild(nm);
                if (!readonly) {
                    const x = document.createElement('button');
                    x.type = 'button'; x.className = 'zt-korr-x';
                    x.innerHTML = '<i class="bx bx-x"></i>';
                    x.addEventListener('click', () => { selected = selected.filter((s) => s !== id); renderChips(); });
                    chip.appendChild(x);
                }
                box.insertBefore(chip, input);
                const h = document.createElement('input');
                h.type = 'hidden'; h.name = 'korrektoren[]'; h.value = id;
                box.appendChild(h);
            });
        }

        function renderList() {
            const q = input.value.trim().toLowerCase();
            const verf = alle.filter((a) => selected.indexOf(a.id) === -1 && (q === '' || a.name.toLowerCase().indexOf(q) !== -1));
            list.innerHTML = '';
            if (verf.length === 0) {
                const li = document.createElement('li');
                li.className = 'zt-korr-empty';
                li.textContent = q ? 'Kein Treffer' : 'Alle ausgewählt';
                list.appendChild(li); list.hidden = false; return;
            }
            verf.forEach((a, i) => {
                const li = document.createElement('li');
                li.dataset.id = a.id; li.textContent = a.name;
                if (i === 0) li.classList.add('zt-korr-active');
                li.addEventListener('mousedown', (e) => { e.preventDefault(); add(a.id); });
                list.appendChild(li);
            });
            list.hidden = false;
        }

        function add(id) { if (selected.indexOf(id) === -1) selected.push(id); input.value = ''; renderChips(); renderList(); input.focus(); }
        function moveActive(dir) {
            const items = [...list.querySelectorAll('li[data-id]')];
            if (!items.length) return;
            let idx = items.findIndex((li) => li.classList.contains('zt-korr-active'));
            items.forEach((li) => li.classList.remove('zt-korr-active'));
            idx = (idx + dir + items.length) % items.length;
            items[idx].classList.add('zt-korr-active');
            items[idx].scrollIntoView({ block: 'nearest' });
        }

        if (!readonly) {
            box.addEventListener('click', () => input.focus());
            input.addEventListener('focus', renderList);
            input.addEventListener('input', renderList);
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const act = list.querySelector('.zt-korr-active') || list.querySelector('li[data-id]');
                    if (act && act.dataset.id) add(Number(act.dataset.id));
                } else if (e.key === 'Backspace' && input.value === '' && selected.length) {
                    selected.pop(); renderChips(); renderList();
                } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault(); moveActive(e.key === 'ArrowDown' ? 1 : -1);
                } else if (e.key === 'Escape') { list.hidden = true; }
            });
            document.addEventListener('click', (e) => { if (!root.contains(e.target)) list.hidden = true; });
        }

        renderChips();
    })();

    // Vergleichs-Modal (Vorher/Nachher).
    (function () {
        const modal = document.getElementById('zt-modal');
        if (!modal) return;
        const feldEl = document.getElementById('zt-modal-feld');
        const zeitEl = document.getElementById('zt-modal-zeit');
        const altEl = document.getElementById('zt-modal-alt');
        const neuEl = document.getElementById('zt-modal-neu');
        const restoreForm = document.getElementById('zt-restore-form');
        const restoreId = document.getElementById('zt-restore-id');
        const close = () => { modal.hidden = true; };

        document.querySelectorAll('.zt-vergleich').forEach((b) => {
            b.addEventListener('click', () => {
                feldEl.textContent = 'Vergleich: ' + (b.dataset.feld || '');
                zeitEl.textContent = b.dataset.zeit ? (b.dataset.zeit + ' Uhr') : '';
                altEl.textContent = b.dataset.alt || '(leer)';
                neuEl.textContent = b.dataset.neu || '(leer)';
                if (restoreForm) {
                    if (b.dataset.restore) { restoreId.value = b.dataset.restore; restoreForm.hidden = false; }
                    else { restoreForm.hidden = true; }
                }
                modal.hidden = false;
            });
        });
        modal.querySelectorAll('[data-close]').forEach((el) => el.addEventListener('click', close));
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
    })();

    // Hinweis beim Speichern: Korrektor(en) ausgewählt, aber der Status würde sie
    // gar nicht sehen lassen (nur „Frei zur Korrektur"/„In Korrektur"/„Korrektur
    // nötig" tauchen bei Korrektoren auf). Der Nutzer kann den Status gleich
    // mitsetzen ODER bewusst nur vormerken.
    (function () {
        const korr = document.getElementById('zt-korr');
        if (!korr) return;                       // nur bei Korrektoren-Auswahl (voll berechtigt)
        const form = korr.closest('form');
        const statusHidden = document.querySelector('#zt-status input[type=hidden]');
        if (!form || !statusHidden) return;

        const SICHTBAR_FUER_KORREKTOR = ['frei_zur_korrektur', 'in_korrektur', 'korrektur_noetig'];
        let bestaetigt = false;

        function setzeStatusFrei() {
            statusHidden.value = 'frei_zur_korrektur';
            const li  = document.querySelector('#zt-status .zt-status-list li[data-value="frei_zur_korrektur"]');
            const btn = document.querySelector('#zt-status .zt-status-btn');
            if (li && btn) {
                const label = btn.querySelector('.zt-status-label');
                const icon  = btn.querySelector('.bx');
                if (label) label.textContent = li.dataset.label;
                if (icon)  { icon.className = 'bx ' + li.dataset.icon; icon.style.color = li.dataset.color; }
            }
        }

        function absenden() { bestaetigt = true; form.submit(); }

        function zeigeHinweis() {
            const back = document.createElement('div');
            back.style.cssText = 'position:fixed;inset:0;z-index:80;display:flex;align-items:center;justify-content:center;padding:1rem;background:rgba(17,24,39,.5)';
            const box = document.createElement('div');
            box.style.cssText = 'background:#fff;border-radius:.75rem;max-width:34rem;width:100%;box-shadow:0 20px 50px -12px rgba(0,0,0,.5);padding:1.25rem';
            box.innerHTML =
                '<div style="display:flex;gap:.6rem;align-items:flex-start">'
              + '<i class="bx bxs-error" style="font-size:1.7rem;color:#f59e0b;line-height:1"></i>'
              + '<div><p style="font-weight:600;color:#1f2937">Korrektor ausgewählt – aber der Status passt (noch) nicht</p>'
              + '<p style="margin-top:.35rem;font-size:.875rem;color:#4b5563;line-height:1.5">Ein zugewiesener Korrektor sieht den Text erst unter „zu&nbsp;Korrigieren", wenn der Status auf <strong>„Frei zur Korrektur"</strong> steht. Beim aktuellen Status wird der Korrektor nur schon einmal vorgemerkt.</p></div></div>';
            const row = document.createElement('div');
            row.style.cssText = 'margin-top:1.1rem;display:flex;flex-wrap:wrap;gap:.5rem;justify-content:flex-end';

            const bSet = document.createElement('button');
            bSet.type = 'button';
            bSet.textContent = 'Status „Frei zur Korrektur" setzen & speichern';
            bSet.style.cssText = 'border:0;border-radius:.5rem;background:#4f46e5;color:#fff;padding:.5rem .9rem;font-size:.875rem;font-weight:500;cursor:pointer';
            bSet.addEventListener('click', () => { setzeStatusFrei(); back.remove(); absenden(); });

            const bOk = document.createElement('button');
            bOk.type = 'button';
            bOk.textContent = 'Verstanden – nur vormerken';
            bOk.style.cssText = 'border:1px solid #d1d5db;border-radius:.5rem;background:#fff;color:#374151;padding:.5rem .9rem;font-size:.875rem;font-weight:500;cursor:pointer';
            bOk.addEventListener('click', () => { back.remove(); absenden(); });

            const bCancel = document.createElement('button');
            bCancel.type = 'button';
            bCancel.textContent = 'Abbrechen';
            bCancel.style.cssText = 'border:0;border-radius:.5rem;background:transparent;color:#6b7280;padding:.5rem .7rem;font-size:.875rem;cursor:pointer';
            bCancel.addEventListener('click', () => back.remove());

            row.appendChild(bCancel); row.appendChild(bOk); row.appendChild(bSet);
            box.appendChild(row); back.appendChild(box); document.body.appendChild(back);
            back.addEventListener('click', (e) => { if (e.target === back) back.remove(); });
            bSet.focus();
        }

        form.addEventListener('submit', function (e) {
            if (bestaetigt) return;
            const anzahl = form.querySelectorAll('input[name="korrektoren[]"]').length;
            if (anzahl > 0 && SICHTBAR_FUER_KORREKTOR.indexOf(statusHidden.value) === -1) {
                e.preventDefault();
                zeigeHinweis();
            }
        });
    })();
</script>
