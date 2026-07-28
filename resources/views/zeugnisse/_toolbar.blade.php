{{--
    Formatierungs-Leiste für Zeugnistext-Felder.

    Hängt sich an jede <textarea data-fmt> und setzt eine B/K/U-Knopfleiste
    davor. Die Formatierung sind leichte Marker im Text (**fett**, *kursiv*,
    __unterstrichen__) – das Feld bleibt eine normale Textarea, damit old(),
    Verlauf, Wiederherstellen und Vergleich unverändert funktionieren. Das
    fertige Aussehen zeigt die Vorschau bzw. das PDF.

    Selbstenthaltend: eigenes plain CSS (bewusst KEINE neuen Tailwind-Klassen,
    damit der Core kein npm-Rebuild braucht). Mehrfaches Einbinden ist harmlos
    (Guard über window-Flag).
--}}
<style>
    .zt-fmt-bar { display: flex; gap: 0.25rem; align-items: center; margin-bottom: 0.25rem; }
    .zt-fmt-btn {
        min-width: 1.9rem; padding: 0.15rem 0.45rem; border: 1px solid #d1d5db; border-radius: 0.5rem;
        background: #fff; color: #374151; font-size: 0.8rem; line-height: 1.4; cursor: pointer;
    }
    .zt-fmt-btn:hover { background: #f9fafb; }
    .zt-fmt-btn.zt-fmt-b { font-weight: 700; }
    .zt-fmt-btn.zt-fmt-i { font-style: italic; }
    .zt-fmt-btn.zt-fmt-u { text-decoration: underline; }
    .zt-fmt-hinweis { margin-left: 0.5rem; font-size: 0.72rem; color: #9ca3af; }
</style>
<script>
(function () {
    if (window.__ztFmtInit) return;
    window.__ztFmtInit = true;

    var MARKER = { b: '**', i: '*', u: '__' };

    function toggle(ta, m) {
        var s = ta.selectionStart, e = ta.selectionEnd, v = ta.value;
        var sel = v.slice(s, e), L = m.length;

        if (s === e) {
            // Leere Auswahl: Markerpaar einfügen, Cursor in die Mitte.
            ta.setRangeText(m + m, s, s, 'end');
            ta.setSelectionRange(s + L, s + L);
        } else if (sel.length >= 2 * L && sel.startsWith(m) && sel.endsWith(m)) {
            // Auswahl ENTHÄLT die Marker (**text**) → entwrappen.
            ta.setRangeText(sel.slice(L, sel.length - L), s, e, 'select');
        } else if (v.slice(s - L, s) === m && v.slice(e, e + L) === m
                   && !(m === '*' && (v.slice(s - 2, s) === '**' || v.slice(e, e + 2) === '**'))) {
            // Marker liegen direkt AUSSEN um die Auswahl → entfernen.
            // (Der *-Sonderfall verhindert, dass ein Kursiv-Klick ein **fett**-Paar halbiert.)
            ta.setRangeText(sel, s - L, e + L, 'select');
        } else {
            ta.setRangeText(m + sel + m, s, e, 'select');
            ta.setSelectionRange(s + L, e + L);
        }

        ta.dispatchEvent(new Event('input', { bubbles: true }));
        ta.focus();
    }

    function baueLeiste(ta) {
        var bar = document.createElement('div');
        bar.className = 'zt-fmt-bar';

        [['b', 'B', 'Fett (Strg+B)', 'zt-fmt-b'],
         ['i', 'K', 'Kursiv (Strg+I)', 'zt-fmt-i'],
         ['u', 'U', 'Unterstrichen (Strg+U)', 'zt-fmt-u']].forEach(function (def) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'zt-fmt-btn ' + def[3];
            btn.textContent = def[1];
            btn.title = def[2];
            btn.addEventListener('click', function () { toggle(ta, MARKER[def[0]]); });
            bar.appendChild(btn);
        });

        var hinweis = document.createElement('span');
        hinweis.className = 'zt-fmt-hinweis';
        hinweis.textContent = 'Marker im Text: **fett**, *kursiv*, __unterstrichen__ – das Aussehen zeigt die Vorschau.';
        bar.appendChild(hinweis);

        ta.parentNode.insertBefore(bar, ta);

        ta.addEventListener('keydown', function (e) {
            if (!(e.ctrlKey || e.metaKey) || e.altKey) return;
            var k = e.key.toLowerCase();
            if (k === 'b' || k === 'i' || k === 'u') {
                e.preventDefault();
                toggle(ta, MARKER[k]);
            }
        });
    }

    document.querySelectorAll('textarea[data-fmt]').forEach(function (ta) {
        if (!ta.disabled && !ta.readOnly) baueLeiste(ta);
    });
})();
</script>
