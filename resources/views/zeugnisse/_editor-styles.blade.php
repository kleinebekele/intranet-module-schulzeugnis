{{-- Gemeinsame Editor-Optik für Abschnitt- UND Klassentext-Editor. --}}
<style>
    .zt-page { max-width: 92rem; }
    .zt-cols { display: grid; gap: 1rem; }
    @media (min-width: 1024px) {
        .zt-cols { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 1.25rem; align-items: start; }
    }

    /* Text-Tabs: Schülertext | Klassenweiter Text – beide Felder bleiben im DOM
       (werden mitgespeichert), nur die Anzeige wechselt. So kann das aktive Feld
       groß dargestellt werden. */
    .zt-txt-tabs { display: flex; gap: .25rem; border-bottom: 1px solid #e5e7eb; margin-bottom: .5rem; }
    .zt-txt-tab {
        display: inline-flex; align-items: center; gap: .45rem; margin-bottom: -1px;
        padding: .5rem .9rem; border: 0; border-bottom: 2px solid transparent;
        background: transparent; cursor: pointer; font-size: .875rem; font-weight: 600; color: #6b7280;
    }
    .zt-txt-tab:hover { color: #374151; }
    .zt-txt-tab.aktiv { color: #4f46e5; border-bottom-color: #4f46e5; }
    .zt-txt-dot { width: .5rem; height: .5rem; border-radius: 9999px; background: #4f46e5; }
    .zt-txt-dot.leer { background: transparent; box-shadow: inset 0 0 0 1px #cbd5e1; }
    .zt-txt-panel[hidden] { display: none; }
    .zt-txt-area { min-height: 20rem; resize: vertical; }

    /* Änderungsverlauf: klar getrennte Zeilen (Divider + Zebra) */
    .zt-log { margin-top: .75rem; padding: 0; list-style: none; border: 1px solid #e5e7eb; border-radius: .5rem; overflow: hidden; }
    .zt-log-item { padding: .625rem .75rem; border-left: 3px solid #e5e7eb; }
    .zt-log-item + .zt-log-item { border-top: 1px solid #e5e7eb; }
    .zt-log-item:nth-child(even) { background: #f9fafb; }
    .zt-log-item.is-restored { border-left-color: #f59e0b; background: #fffbeb; }

    /* Status + Korrektoren nebeneinander (einzeln = volle Breite) */
    .zt-two { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-start; }
    .zt-two > * { flex: 1 1 260px; min-width: 0; }

    /* Korrektoren – durchsuchbare Mehrfachauswahl mit Chips */
    .zt-korr { position: relative; margin-top: .25rem; }
    .zt-korr-box {
        display: flex; flex-wrap: wrap; gap: .35rem; align-items: center; min-height: 2.5rem;
        padding: .3rem .45rem; border: 1px solid #d1d5db; border-radius: .5rem; background: #fff;
        box-shadow: 0 1px 2px rgba(0,0,0,.05); cursor: text;
    }
    .zt-korr-box:focus-within { border-color: #6366f1; box-shadow: 0 0 0 1px #6366f1; }
    .zt-korr-chip {
        display: inline-flex; align-items: center; gap: .25rem; background: #eef2ff; color: #4338ca;
        border-radius: 9999px; padding: .15rem .3rem .15rem .6rem; font-size: .8rem; font-weight: 500;
    }
    .zt-korr-x { display: inline-flex; align-items: center; border: 0; background: transparent; color: #6366f1; cursor: pointer; padding: 0; border-radius: 9999px; }
    .zt-korr-x:hover { color: #4338ca; background: rgba(99,102,241,.15); }
    .zt-korr-input { flex: 1; min-width: 8rem; border: 0; outline: none; background: transparent; font-size: .875rem; padding: .2rem; color: #374151; }
    .zt-korr-list {
        position: absolute; z-index: 40; left: 0; right: 0; top: calc(100% + 4px);
        margin: 0; padding: 4px; list-style: none; background: #fff; border: 1px solid #e5e7eb;
        border-radius: .5rem; box-shadow: 0 12px 30px -8px rgba(0,0,0,.35); max-height: 240px; overflow: auto;
    }
    .zt-korr-list li { padding: .4rem .55rem; border-radius: .375rem; font-size: .875rem; color: #374151; cursor: pointer; }
    .zt-korr-list li.zt-korr-active, .zt-korr-list li:not(.zt-korr-empty):hover { background: #eef2ff; }
    .zt-korr-empty { color: #9ca3af; font-size: .8rem; cursor: default; }
    .zt-korr-ro .zt-korr-box { background: #f9fafb; cursor: default; }

    /* Eigenes Status-Dropdown mit Icon + Farbe */
    .zt-status { position: relative; margin-top: .25rem; }
    .zt-status-btn {
        display: flex; align-items: center; gap: .5rem; width: 100%;
        border: 1px solid #d1d5db; border-radius: .5rem; background: #fff;
        padding: .5rem .75rem; font-size: .875rem; color: #374151; text-align: left;
        box-shadow: 0 1px 2px rgba(0,0,0,.05); cursor: pointer;
    }
    .zt-status-btn:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 1px #6366f1; }
    .zt-status-btn > .bx:first-child { font-size: 1.125rem; }
    .zt-status-caret { margin-left: auto; color: #9ca3af; }
    .zt-status-list {
        position: absolute; z-index: 40; left: 0; right: 0; top: calc(100% + 4px);
        margin: 0; padding: 4px; list-style: none;
        background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem;
        box-shadow: 0 12px 30px -8px rgba(0,0,0,.35); max-height: 320px; overflow: auto;
    }
    .zt-status-list li {
        display: flex; align-items: center; gap: .5rem;
        padding: .4rem .5rem; border-radius: .375rem; font-size: .875rem; color: #374151; cursor: pointer;
    }
    .zt-status-list li:hover { background: #eef2ff; }
    .zt-status-list li .bx { font-size: 1.125rem; }
    .zt-status-ro .zt-status-btn { background: #f9fafb; cursor: default; }
    .zt-status-ro .zt-status-caret { display: none; }

    /* Vergleichs-Modal */
    .zt-modal { position: fixed; inset: 0; z-index: 70; display: flex; align-items: center; justify-content: center; padding: 1rem; }
    .zt-modal[hidden] { display: none; }
    .zt-modal-backdrop { position: absolute; inset: 0; background: rgba(17,24,39,.5); }
    .zt-modal-box {
        position: relative; background: #fff; border-radius: .75rem;
        box-shadow: 0 20px 50px -12px rgba(0,0,0,.5);
        width: 100%; max-width: 900px; max-height: 85vh; display: flex; flex-direction: column;
    }
    .zt-modal-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: .9rem 1.1rem; border-bottom: 1px solid #eee; }
    .zt-modal-x { color: #9ca3af; line-height: 1; }
    .zt-modal-x:hover { color: #374151; }
    .zt-modal-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 1px; background: #eee; overflow: hidden; border-radius: 0 0 .75rem .75rem; flex: 1; min-height: 0; }
    .zt-modal-col { background: #fff; display: flex; flex-direction: column; min-height: 0; }
    .zt-modal-label { font-size: 10px; text-transform: uppercase; letter-spacing: .05em; font-weight: 600; color: #9ca3af; padding: .55rem .9rem .1rem; }
    .zt-modal-pre { white-space: pre-wrap; word-break: break-word; font-size: .875rem; color: #374151; line-height: 1.5; padding: .2rem .9rem 1rem; overflow: auto; }
    .zt-modal-restore { padding: 0 .9rem .9rem; border-top: 1px solid #f3f4f6; padding-top: .7rem; }
    @media (max-width: 640px) { .zt-modal-cols { grid-template-columns: 1fr; } }
</style>
