@php
    $titel = $abschnitt->typ === 'haupttext' ? 'Haupttext' : ($abschnitt->fach?->name ?? 'Fachtext');
    $istNote = $abschnitt->typ === 'note';
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-module-icon name="book" class="text-2xl text-indigo-600" />
                <div>
                    <h1 class="text-xl font-semibold text-gray-800">{{ $titel }}</h1>
                    <p class="text-sm text-gray-500">
                        {{ $schueler?->fullName() }} &middot; Klasse {{ $schueler?->klasse?->name ?? '—' }} &middot; Schuljahr {{ $schueler?->klasse?->schuljahr?->name ?? '—' }}
                    </p>
                </div>
            </div>
            @if ($readonly)
                <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-700">Zeugnis abgeschlossen</span>
            @endif
        </div>
    </x-slot>

    <div class="space-y-4 zt-page">
        <div class="flex items-center justify-between">
            <a href="{{ route('module.schulzeugnis.zeugnisse.index', $schueler?->klasse) }}"
               class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                &larr; Zurück zur Zeugnis-Tabelle
            </a>
            <a href="{{ route('module.schulzeugnis.zeugnisse.edit', $zeugnis) }}"
               class="text-sm text-gray-500 hover:text-gray-700">Ganzes Zeugnis &rarr;</a>
        </div>

        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ session('error') }}</div>
        @endif

        @if ($zeugnis->istAbgeschlossen())
            <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                Das Zeugnis ist abgeschlossen – der Text ist schreibgeschützt.
            </div>
        @elseif ($berechtigung === 'korrektor')
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">
                Du bist als <strong>Korrektor:in</strong> für diesen Text zugewiesen. Du kannst den Text korrigieren und den Status auf „In Korrektur" oder „Korrektur durchgeführt" setzen.
            </div>
        @elseif ($berechtigung === 'keine')
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
                Nur-Ansicht – du bist für diesen Text nicht berechtigt.
            </div>
        @endif

        <div class="zt-cols">
        <div class="zt-main space-y-4">
        {{-- Bearbeitung --}}
        <form method="POST" action="{{ route('module.schulzeugnis.abschnitte.update', $abschnitt) }}"
              class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            @csrf
            @method('PUT')

            @if ($abschnitt->autor_name)
                <p class="text-xs text-gray-400">Autor: {{ $abschnitt->autor_name }}</p>
            @endif

            @if ($klassentext && $berechtigung === 'voll')
                <div class="rounded-lg bg-indigo-50/60 p-3">
                    <label class="block text-sm font-medium text-gray-700">Klassenweiter Text
                        <textarea name="klassentext" rows="3" @disabled($readonly)
                                  class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Gemeinsamer Text für alle Schüler …">{{ old('klassentext', $klassentext->text) }}</textarea>
                    </label>
                    <p class="mt-1 text-xs text-gray-500">Gilt für <strong>alle Schüler</strong> der Klasse und steht auf dem Zeugnis <strong>vor</strong> dem individuellen Text.</p>
                    <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="klassentext_neue_zeile" value="1" @checked($abschnitt->klassentext_neue_zeile) @disabled($readonly)
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Schülertext in neuer Zeile beginnen (statt direkt anschließen)
                    </label>
                </div>
            @endif

            @if ($istNote)
                <label class="block text-sm font-medium text-gray-700">Note
                    <input type="text" name="note" value="{{ old('note', $abschnitt->note) }}" @disabled($readonly)
                           class="mt-1 block w-32 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <label class="block text-sm font-medium text-gray-700">Ergänzung (optional)
                    <input type="text" name="inhalt" value="{{ old('inhalt', $abschnitt->inhalt) }}" @disabled($readonly)
                           class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
            @else
                <label class="block text-sm font-medium text-gray-700">{{ $klassentext ? 'Schülertext' : 'Text' }}
                    <textarea name="inhalt" rows="8" @disabled($readonly)
                              class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Text …">{{ old('inhalt', $abschnitt->inhalt) }}</textarea>
                </label>
            @endif

            @if ($berechtigung === 'voll')
                <label class="block text-sm font-medium text-gray-700">Notiz <span class="text-gray-400">(intern, erscheint nicht auf dem Zeugnis)</span>
                    <textarea name="notiz" rows="2" @disabled($readonly)
                              class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="z. B. Rückfrage, Erinnerung …">{{ old('notiz', $abschnitt->notiz) }}</textarea>
                </label>
            @endif

            <label class="block text-sm font-medium text-gray-700">Bearbeitungsstatus
                @php $statusOptionen = $berechtigung === 'korrektor' ? collect($stati)->only($korrekturStati) : $stati; @endphp
                <select name="status" @disabled($readonly)
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($statusOptionen as $key => $meta)
                        <option value="{{ $key }}" @selected(old('status', $abschnitt->status) === $key)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </label>

            @if ($berechtigung === 'voll')
                <div>
                    <label class="block text-sm font-medium text-gray-700">Korrektoren <span class="text-gray-400">(dürfen diesen Text korrigieren)</span></label>
                    <select name="korrektoren[]" multiple size="5" @disabled($readonly)
                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($alleLehrer as $l)
                            <option value="{{ $l->id }}" @selected(in_array($l->id, old('korrektoren', $korrektorIds)))>{{ $l->fullName() }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-400">Mehrfachauswahl (Strg/Cmd). Pflicht, wenn du den Status auf „Frei zur Korrektur" oder „Korrektur nötig" setzt.</p>
                </div>
            @endif

            @unless ($readonly)
                @php
                    $vorauswahl = $navNext ? 'next' : ($navPrev ? 'prev' : 'index');
                    $urlPrev  = $navPrev ? route('module.schulzeugnis.abschnitte.edit', $navPrev['id']) : '';
                    $urlNext  = $navNext ? route('module.schulzeugnis.abschnitte.edit', $navNext['id']) : '';
                    $urlIndex = route('module.schulzeugnis.zeugnisse.index', $schueler?->klasse);
                    $labelNext = $navNext ? 'Nächster Schüler: ' . $navNext['name'] : 'Nächster Schüler (keiner)';
                    $labelPrev = $navPrev ? 'Vorheriger Schüler: ' . $navPrev['name'] : 'Vorheriger Schüler (keiner)';
                @endphp
                <div class="space-y-3 border-t border-gray-100 pt-4" id="zt-nav">
                    <p class="text-sm font-medium text-gray-700">Danach weiter zu:</p>
                    <div class="space-y-1.5 text-sm">
                        <label class="flex items-center gap-2 {{ $navNext ? 'text-gray-700' : 'text-gray-400' }}">
                            <input type="radio" name="weiter" value="next" data-url="{{ $urlNext }}"
                                   @checked($vorauswahl === 'next') @disabled(! $navNext)
                                   class="text-indigo-600 focus:ring-indigo-500">
                            <i class="bx bx-right-arrow-alt text-lg text-indigo-500"></i>
                            <span>{{ $labelNext }}</span>
                        </label>
                        <label class="flex items-center gap-2 {{ $navPrev ? 'text-gray-700' : 'text-gray-400' }}">
                            <input type="radio" name="weiter" value="prev" data-url="{{ $urlPrev }}"
                                   @checked($vorauswahl === 'prev') @disabled(! $navPrev)
                                   class="text-indigo-600 focus:ring-indigo-500">
                            <i class="bx bx-left-arrow-alt text-lg text-indigo-500"></i>
                            <span>{{ $labelPrev }}</span>
                        </label>
                        <label class="flex items-center gap-2 text-gray-700">
                            <input type="radio" name="weiter" value="index" data-url="{{ $urlIndex }}"
                                   @checked($vorauswahl === 'index')
                                   class="text-indigo-600 focus:ring-indigo-500">
                            <i class="bx bx-list-ul text-lg text-indigo-500"></i>
                            Zurück zur Übersicht
                        </label>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            <i class="bx bx-save text-lg"></i> Speichern
                        </button>
                        <button type="button" id="zt-cancel"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="bx bx-x text-lg"></i> Abbrechen
                        </button>
                        @if ($navGesamt)
                            <span class="ml-auto text-xs text-gray-400">Schüler {{ $navPosition }} von {{ $navGesamt }} in {{ $titel }}</span>
                        @endif
                    </div>
                </div>
            @endunless
        </form>

        {{-- Blättern im Nur-Ansicht-Modus (kein Speichern nötig) --}}
        @if ($readonly && ($navPrev || $navNext))
            <div class="flex items-center justify-between gap-2">
                <span>
                    @if ($navPrev)
                        <a href="{{ route('module.schulzeugnis.abschnitte.edit', $navPrev['id']) }}"
                           class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            &larr; {{ $navPrev['name'] }}
                        </a>
                    @endif
                </span>
                @if ($navGesamt)
                    <span class="text-xs text-gray-400">Schüler {{ $navPosition }} von {{ $navGesamt }}</span>
                @endif
                <span>
                    @if ($navNext)
                        <a href="{{ route('module.schulzeugnis.abschnitte.edit', $navNext['id']) }}"
                           class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            {{ $navNext['name'] }} &rarr;
                        </a>
                    @endif
                </span>
            </div>
        @endif
        </div>{{-- /zt-main --}}

        <div class="zt-side">
        {{-- Änderungsverlauf / Wiederherstellung --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-gray-700">Änderungsverlauf</h2>
            @if ($verlauf->isEmpty())
                <p class="mt-2 text-sm text-gray-400">Noch keine Textänderungen protokolliert.</p>
            @else
                <ul class="mt-3 space-y-3">
                    @foreach ($verlauf as $eintrag)
                        <li class="border-l-2 border-gray-200 pl-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-xs text-gray-500">
                                    {{ $eintrag->created_at?->format('d.m.Y H:i') }} Uhr
                                    @if ($eintrag->akteur_name) &middot; {{ $eintrag->akteur_name }} @endif
                                    @if ($eintrag->aktion === 'abschnitt_wiederhergestellt')
                                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700">wiederhergestellt</span>
                                    @endif
                                </div>
                                @unless ($readonly)
                                    <form method="POST" action="{{ route('module.schulzeugnis.abschnitte.wiederherstellen', $abschnitt) }}"
                                          onsubmit="return confirm('Diesen Stand wiederherstellen? Der aktuelle Text wird ersetzt (bleibt im Verlauf).');">
                                        @csrf
                                        <input type="hidden" name="protokoll_id" value="{{ $eintrag->id }}">
                                        <button type="submit" class="text-xs text-indigo-600 hover:underline">Wiederherstellen</button>
                                    </form>
                                @endunless
                            </div>
                            <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ \Illuminate\Support\Str::limit($eintrag->neu_wert, 240) ?: '(leer)' }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        </div>{{-- /zt-side --}}
        </div>{{-- /zt-cols --}}
    </div>

    <style>
        .zt-page { max-width: 92rem; }
        .zt-cols { display: grid; gap: 1rem; }
        @media (min-width: 1024px) {
            .zt-cols { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 1.25rem; align-items: start; }
        }
    </style>
    <script>
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
    </script>
</x-app-layout>
