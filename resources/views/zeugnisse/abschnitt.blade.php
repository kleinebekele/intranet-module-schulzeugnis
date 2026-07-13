@php
    $titel = $abschnitt->typ === 'hauptzeugnis'
        ? 'Hauptzeugnis'
        : ($abschnitt->typ === 'haupttext' ? 'Haupttext' : ($abschnitt->fach?->name ?? 'Fachtext'));
    $istNote = $abschnitt->typ === 'note';
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <x-module-icon name="book" class="shrink-0 text-2xl text-indigo-600" />
                <h1 class="truncate text-3xl font-bold tracking-tight text-gray-800">{{ $schueler?->fullName() ?: $titel }}</h1>
            </div>
            <div class="shrink-0 text-right">
                <div class="text-base font-semibold text-gray-700">{{ $titel }}</div>
                <div class="text-sm text-gray-500">
                    Klasse {{ $schueler?->klasse?->name ?? '—' }} &middot; Schuljahr {{ $schueler?->klasse?->schuljahr?->name ?? '—' }}
                </div>
                @if ($readonly)
                    <span class="mt-1 inline-block rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-700">Zeugnis abgeschlossen</span>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="space-y-4 zt-page">
        <div class="flex items-center justify-between">
            <a href="{{ $zurueck['url'] }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                <i class="bx bx-arrow-back text-lg"></i>
                <i class="bx {{ $zurueck['icon'] }} text-lg"></i>
                Zurück zu {{ $zurueck['label'] }}
            </a>
            <a href="{{ route('module.schulzeugnis.klassenraeume.zeugnisse.edit', $zeugnis) }}"
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
            <div class="flex items-center gap-3 rounded-xl border-2 border-amber-400 bg-amber-50 p-4 text-amber-900 shadow-sm">
                <i class="bx bxs-error text-3xl text-amber-500"></i>
                <div>
                    <p class="font-bold">Nur-Ansicht</p>
                    <p class="text-sm">Du bist für diesen Text nicht berechtigt und kannst ihn nicht bearbeiten.</p>
                </div>
            </div>
        @endif

        <div class="zt-cols">
        <div class="zt-main space-y-4">
        {{-- Bearbeitung --}}
        <form method="POST" action="{{ route('module.schulzeugnis.klassenraeume.abschnitte.update', $abschnitt) }}"
              class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            @csrf
            @method('PUT')
            <input type="hidden" name="quelle" value="{{ $quelle }}">

            @if ($abschnitt->autor_name)
                <p class="text-xs text-gray-400">Autor: {{ $abschnitt->autor_name }}</p>
            @endif

            @php
                // Zwei Textfelder (Schülertext + Klassentext) in Tabs, damit das aktive
                // Feld groß dargestellt werden kann – nur wenn es beide gibt und keine Note.
                $hatTextTabs = $klassentext && $berechtigung === 'voll' && ! $istNote;
            @endphp

            @if ($istNote)
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
                <label class="block text-sm font-medium text-gray-700">Note
                    <input type="text" name="note" value="{{ old('note', $abschnitt->note) }}" @disabled($readonly)
                           class="mt-1 block w-32 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <label class="block text-sm font-medium text-gray-700">Ergänzung (optional)
                    <input type="text" name="inhalt" value="{{ old('inhalt', $abschnitt->inhalt) }}" @disabled($readonly)
                           class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
            @elseif ($abschnitt->typ === 'hauptzeugnis')
                {{-- Hauptzeugnis: je Fachbereich ein Schülertext-Tab + ein Klassentext-Tab. --}}
                @php $ktGefuellt = $klassentext && trim((string) old('klassentext', $klassentext->text ?? '')) !== ''; @endphp
                <div class="zt-txt" id="zt-txt">
                    <div class="zt-txt-tabs" role="tablist">
                        @foreach ($bereichtexte as $bt)
                            <button type="button" class="zt-txt-tab" data-txt="b{{ $bt->id }}" role="tab">{{ $bt->ueberschrift() }}</button>
                        @endforeach
                        @if ($klassentext && $berechtigung === 'voll')
                            <button type="button" class="zt-txt-tab" data-txt="klasse" role="tab">
                                Klassenweiter Text
                                <span class="zt-txt-dot {{ $ktGefuellt ? '' : 'leer' }}" title="{{ $ktGefuellt ? 'enthält Text' : 'noch leer' }}"></span>
                            </button>
                        @endif
                    </div>

                    @foreach ($bereichtexte as $bt)
                        <div class="zt-txt-panel" data-txt="b{{ $bt->id }}" @unless ($loop->first) hidden @endunless>
                            <textarea name="bereichtexte[{{ $bt->id }}][inhalt]" rows="16" @disabled($readonly)
                                      class="zt-txt-area mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                      placeholder="Text für „{{ $bt->ueberschrift() }}" …">{{ old('bereichtexte.' . $bt->id . '.inhalt', $bt->inhalt) }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">Text für <strong>diesen Schüler</strong> im Bereich „{{ $bt->ueberschrift() }}".</p>
                        </div>
                    @endforeach

                    @if ($klassentext && $berechtigung === 'voll')
                        <div class="zt-txt-panel" data-txt="klasse" hidden>
                            <textarea name="klassentext" rows="16" @disabled($readonly)
                                      class="zt-txt-area mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                      placeholder="Gemeinsamer Text für alle Schüler …">{{ old('klassentext', $klassentext->text) }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">Gilt für <strong>alle Schüler</strong> der Klasse und steht auf dem Zeugnis <strong>vor</strong> den individuellen Texten.</p>
                            <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-600">
                                <input type="checkbox" name="klassentext_neue_zeile" value="1" @checked($abschnitt->klassentext_neue_zeile) @disabled($readonly)
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                Schülertext in neuer Zeile beginnen (statt direkt anschließen)
                            </label>
                        </div>
                    @endif
                </div>
            @elseif ($hatTextTabs)
                @php $klassentextGefuellt = trim((string) old('klassentext', $klassentext->text)) !== ''; @endphp
                <div class="zt-txt" id="zt-txt">
                    <div class="zt-txt-tabs" role="tablist">
                        <button type="button" class="zt-txt-tab" data-txt="schueler" role="tab">Schülertext</button>
                        <button type="button" class="zt-txt-tab" data-txt="klasse" role="tab">
                            Klassenweiter Text
                            <span class="zt-txt-dot {{ $klassentextGefuellt ? '' : 'leer' }}"
                                  title="{{ $klassentextGefuellt ? 'enthält Text' : 'noch leer' }}"></span>
                        </button>
                    </div>

                    {{-- Tab 1: Schülertext (individuell) --}}
                    <div class="zt-txt-panel" data-txt="schueler">
                        <textarea name="inhalt" rows="16" @disabled($readonly)
                                  class="zt-txt-area mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Individueller Text für diesen Schüler …">{{ old('inhalt', $abschnitt->inhalt) }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">Individueller Text für <strong>diesen Schüler</strong>.</p>
                    </div>

                    {{-- Tab 2: Klassenweiter Text (+ Umbruch-Option) --}}
                    <div class="zt-txt-panel" data-txt="klasse" hidden>
                        <textarea name="klassentext" rows="16" @disabled($readonly)
                                  class="zt-txt-area mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Gemeinsamer Text für alle Schüler …">{{ old('klassentext', $klassentext->text) }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">Gilt für <strong>alle Schüler</strong> der Klasse und steht auf dem Zeugnis <strong>vor</strong> dem individuellen Text.</p>
                        <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" name="klassentext_neue_zeile" value="1" @checked($abschnitt->klassentext_neue_zeile) @disabled($readonly)
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            Schülertext in neuer Zeile beginnen (statt direkt anschließen)
                        </label>
                    </div>
                </div>
            @else
                <label class="block text-sm font-medium text-gray-700">{{ $klassentext ? 'Schülertext' : 'Text' }}
                    <textarea name="inhalt" rows="8" @disabled($readonly)
                              class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Text …">{{ old('inhalt', $abschnitt->inhalt) }}</textarea>
                </label>
            @endif

            @if (in_array($berechtigung, ['voll', 'korrektor']))
                <label class="block text-sm font-medium text-gray-700">Notiz <span class="text-gray-400">(intern, erscheint nicht auf dem Zeugnis)</span>
                    <textarea name="notiz" rows="2" @disabled($readonly)
                              class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="z. B. Rückfrage, Erinnerung …">{{ old('notiz', $abschnitt->notiz) }}</textarea>
                </label>
            @endif

            @php
                $statusOptionen = $berechtigung === 'korrektor' ? collect($stati)->only($korrekturStati) : $stati;
                $statusFarbe = ['gray' => '#9ca3af', 'amber' => '#f59e0b', 'red' => '#ef4444', 'green' => '#16a34a'];
                $aktStatus = old('status', $abschnitt->status);
                $aktMeta = $stati[$aktStatus] ?? ['label' => '—', 'icon' => 'bx-circle', 'farbe' => 'gray'];
            @endphp
            <div class="zt-two">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Bearbeitungsstatus</label>
                    <div class="zt-status {{ $readonly ? 'zt-status-ro' : '' }}" id="zt-status">
                        <input type="hidden" name="status" value="{{ $aktStatus }}">
                        <button type="button" class="zt-status-btn" @disabled($readonly)>
                            <i class="bx {{ $aktMeta['icon'] }}" style="color: {{ $statusFarbe[$aktMeta['farbe']] ?? '#9ca3af' }}"></i>
                            <span class="zt-status-label">{{ $aktMeta['label'] }}</span>
                            <i class="bx bx-chevron-down zt-status-caret"></i>
                        </button>
                        <ul class="zt-status-list" hidden>
                            @foreach ($statusOptionen as $key => $meta)
                                <li data-value="{{ $key }}" data-icon="{{ $meta['icon'] }}"
                                    data-color="{{ $statusFarbe[$meta['farbe']] ?? '#9ca3af' }}" data-label="{{ $meta['label'] }}">
                                    <i class="bx {{ $meta['icon'] }}" style="color: {{ $statusFarbe[$meta['farbe']] ?? '#9ca3af' }}"></i>
                                    {{ $meta['label'] }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                @if ($berechtigung === 'voll')
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Korrektoren <span class="text-gray-400">(dürfen diesen Text korrigieren)</span></label>
                        <div id="zt-korr" class="zt-korr {{ $readonly ? 'zt-korr-ro' : '' }}">
                            <div class="zt-korr-box">
                                {{-- Chips + versteckte korrektoren[]-Felder werden per JS eingefügt --}}
                                <input type="text" class="zt-korr-input" placeholder="Lehrer suchen …" autocomplete="off" @disabled($readonly)>
                            </div>
                            <ul class="zt-korr-list" hidden></ul>
                        </div>
                        <script type="application/json" id="zt-korr-data">@json($alleLehrer->map(fn ($l) => ['id' => $l->id, 'name' => $l->fullName()])->values())</script>
                        <script type="application/json" id="zt-korr-selected">@json(collect(old('korrektoren', $korrektorIds))->map(fn ($v) => (int) $v)->values())</script>
                        <p class="mt-1 text-xs text-gray-400">Tippen zum Suchen, Klick zum Hinzufügen. Pflicht, wenn du den Status auf „Frei zur Korrektur" oder „Korrektur nötig" setzt.</p>
                    </div>
                @endif
            </div>

            @unless ($readonly)
                @php
                    $vorauswahl = $navNext ? 'next' : ($navPrev ? 'prev' : 'index');
                    $urlPrev  = $navPrev ? route('module.schulzeugnis.klassenraeume.abschnitte.edit', ['abschnitt' => $navPrev['id'], 'quelle' => $quelle]) : '';
                    $urlNext  = $navNext ? route('module.schulzeugnis.klassenraeume.abschnitte.edit', ['abschnitt' => $navNext['id'], 'quelle' => $quelle]) : '';
                    $urlIndex = $zurueck['url'];
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
                        @if ($navPrev)
                            <label class="flex items-center gap-2 text-gray-700">
                                <input type="radio" name="weiter" value="prev" data-url="{{ $urlPrev }}"
                                       @checked($vorauswahl === 'prev')
                                       class="text-indigo-600 focus:ring-indigo-500">
                                <i class="bx bx-left-arrow-alt text-lg text-indigo-500"></i>
                                <span>{{ $labelPrev }}</span>
                            </label>
                        @elseif (! empty($klassentextZeileUrl))
                            {{-- Erster Schüler: eine Zeile hoch = Klassenweit-Zeile desselben Fachs. --}}
                            <label class="flex items-center gap-2 text-gray-700">
                                <input type="radio" name="weiter" value="klassentext" data-url="{{ $klassentextZeileUrl }}"
                                       class="text-indigo-600 focus:ring-indigo-500">
                                <i class="bx bx-up-arrow-alt text-lg text-indigo-500"></i>
                                <span>Vorherige Zeile: Klassentext</span>
                            </label>
                        @else
                            <label class="flex items-center gap-2 text-gray-400">
                                <input type="radio" name="weiter" value="prev" disabled
                                       class="text-indigo-600 focus:ring-indigo-500">
                                <i class="bx bx-left-arrow-alt text-lg text-indigo-500"></i>
                                <span>{{ $labelPrev }}</span>
                            </label>
                        @endif
                        <label class="flex items-center gap-2 text-gray-700">
                            <input type="radio" name="weiter" value="index" data-url="{{ $urlIndex }}"
                                   @checked($vorauswahl === 'index')
                                   class="text-indigo-600 focus:ring-indigo-500">
                            <i class="bx {{ $zurueck['icon'] }} text-lg text-indigo-500"></i>
                            Zurück zu {{ $zurueck['label'] }}
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

        {{-- Korrektor: Korrektur ablehnen (entfernt sich selbst, Status zurück auf „Frei zur Korrektur"). --}}
        @if ($berechtigung === 'korrektor' && ! $readonly)
            <form method="POST" action="{{ route('module.schulzeugnis.klassenraeume.abschnitte.ablehnen', $abschnitt) }}"
                  class="rounded-xl border border-red-100 bg-red-50/40 p-4"
                  onsubmit="return confirm('Korrektur ablehnen? Du wirst als Korrektor entfernt und der Text geht als „Frei zur Korrektur“ zurück an die Lehrkraft.');">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-100">
                    <i class="bx bx-x-circle text-lg"></i> Korrektur ablehnen
                </button>
                <p class="mt-1 text-xs text-gray-500">Entfernt dich als Korrektor und setzt den Status zurück auf „Frei zur Korrektur" – die Lehrkraft wählt dann jemand anderen.</p>
            </form>
        @endif

        {{-- Blättern im Nur-Ansicht-Modus (kein Speichern nötig) --}}
        @if ($readonly && ($navPrev || $navNext))
            <div class="flex items-center justify-between gap-2">
                <span>
                    @if ($navPrev)
                        <a href="{{ route('module.schulzeugnis.klassenraeume.abschnitte.edit', ['abschnitt' => $navPrev['id'], 'quelle' => $quelle]) }}"
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
                        <a href="{{ route('module.schulzeugnis.klassenraeume.abschnitte.edit', ['abschnitt' => $navNext['id'], 'quelle' => $quelle]) }}"
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
                <p class="mt-2 text-sm text-gray-400">Noch keine Änderungen protokolliert.</p>
            @else
                <ul class="zt-log">
                    @foreach ($verlauf as $e)
                        <li class="zt-log-item {{ $e['wiederhergestellt'] ? 'is-restored' : '' }}">
                            <div class="text-xs text-gray-500">
                                {{ $e['zeit']?->format('d.m.Y H:i') }} Uhr
                                @if ($e['akteur']) &middot; {{ $e['akteur'] }} @endif
                                @if ($e['wiederhergestellt'])
                                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700">wiederhergestellt</span>
                                @endif
                            </div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-1 text-sm">
                                <span class="font-medium text-gray-700">{{ $e['feld'] }}</span>
                                @if ($e['istStatus'] && $e['status'])
                                    <span class="text-gray-400">—</span>
                                    <span class="inline-flex items-center gap-1 text-gray-600">
                                        <i class="bx {{ $e['status']['altIcon'] }}" style="color: {{ $e['status']['altColor'] }}"></i>{{ $e['status']['altLabel'] }}
                                    </span>
                                    <i class="bx bx-right-arrow-alt text-gray-400"></i>
                                    <span class="inline-flex items-center gap-1 text-gray-700">
                                        <i class="bx {{ $e['status']['neuIcon'] }}" style="color: {{ $e['status']['neuColor'] }}"></i>{{ $e['status']['neuLabel'] }}
                                    </span>
                                @elseif (! empty($e['istMeta']))
                                    {{-- Korrektor-Änderung: der Feld-Text oben genügt --}}
                                @else
                                    <span class="{{ $e['wiederhergestellt'] ? 'text-amber-700' : 'text-gray-500' }}">— {{ $e['summary'] }}</span>
                                @endif
                            </div>
                            @unless ($e['istStatus'] || ! empty($e['istMeta']))
                                <div class="mt-1 text-xs">
                                    <button type="button" class="zt-vergleich inline-flex items-center gap-1 text-indigo-600 hover:underline"
                                            data-feld="{{ $e['feld'] }}" data-zeit="{{ $e['zeit']?->format('d.m.Y H:i') }}"
                                            data-alt="{{ $e['alt'] }}" data-neu="{{ $e['neu'] }}"
                                            @if (! $readonly && $e['restorable']) data-restore="{{ $e['id'] }}" @endif>
                                        <i class="bx bx-git-compare"></i> Vergleichen
                                    </button>
                                </div>
                            @endunless
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        </div>{{-- /zt-side --}}
        </div>{{-- /zt-cols --}}
    </div>

    {{-- Vergleichs-Modal (Vorher/Nachher nebeneinander) --}}
    <div id="zt-modal" class="zt-modal" hidden>
        <div class="zt-modal-backdrop" data-close></div>
        <div class="zt-modal-box">
            <div class="zt-modal-head">
                <div>
                    <div class="text-sm font-semibold text-gray-800" id="zt-modal-feld">Vergleich</div>
                    <div class="text-xs text-gray-400" id="zt-modal-zeit"></div>
                </div>
                <button type="button" class="zt-modal-x" data-close aria-label="Schließen"><i class="bx bx-x text-2xl"></i></button>
            </div>
            <div class="zt-modal-cols">
                <div class="zt-modal-col">
                    <div class="zt-modal-label">Vorher</div>
                    <div class="zt-modal-pre" id="zt-modal-alt"></div>
                    @unless ($readonly)
                        <form id="zt-restore-form" method="POST" action="{{ route('module.schulzeugnis.klassenraeume.abschnitte.wiederherstellen', $abschnitt) }}"
                              class="zt-modal-restore" hidden
                              onsubmit="return confirm('Den Vorher-Stand wiederherstellen? Der aktuelle Text wird dadurch ersetzt (bleibt im Verlauf).');">
                            @csrf
                            <input type="hidden" name="protokoll_id" id="zt-restore-id" value="">
                            <button type="submit"
                                    class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100">
                                <i class="bx bx-undo"></i> Diesen Vorher-Text wiederherstellen
                            </button>
                        </form>
                    @endunless
                </div>
                <div class="zt-modal-col">
                    <div class="zt-modal-label">Nachher</div>
                    <div class="zt-modal-pre" id="zt-modal-neu"></div>
                </div>
            </div>
        </div>
    </div>

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
    </script>
</x-app-layout>
