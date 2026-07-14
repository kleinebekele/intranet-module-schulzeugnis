@php
    $istSpruch = ($fachParam ?? '') === 'spruch';
    $istHaupt  = ($fachParam ?? '') === 'haupt';
    // "keine Fachbereiche" = kein Bereich außer dem Pflicht-Standard „Allgemein".
    $ohneBereiche = $istHaupt && $klasse->hauptbereiche
        ->reject(fn ($b) => $b->name === \Intranet\Modules\Schulzeugnis\Models\Hauptbereich::STANDARD)
        ->isEmpty();
    $titel   = $bezeichnung ?? ($fach?->name ?? 'Haupttext (Klassenlehrer)');
    $indexUrl = route('module.schulzeugnis.klassenraeume.zeugnisse.index', $klasse);
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <x-module-icon name="book" class="shrink-0 text-2xl text-indigo-600" />
                <h1 class="truncate text-3xl font-bold tracking-tight text-gray-800">Klassenweiter Text</h1>
            </div>
            <div class="shrink-0 text-right">
                <div class="text-base font-semibold text-gray-700">{{ $titel }}</div>
                <div class="text-sm text-gray-500">
                    {{ $klasse->name }} &middot; Schuljahr {{ $klasse->schuljahr->name }}
                </div>
            </div>
        </div>
    </x-slot>

    <div class="space-y-4 zt-page">
        <div class="flex items-center justify-between">
            <a href="{{ $indexUrl }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                <i class="bx bx-arrow-back text-lg"></i>
                <i class="bx bx-table text-lg"></i>
                Zurück zur Zeugnis-Tabelle
            </a>
        </div>

        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ session('error') }}</div>
        @endif

        @if ($berechtigung === 'korrektor')
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">
                Du bist als <strong>Korrektor:in</strong> für diesen klassenweiten Text zugewiesen. Du kannst den Text korrigieren und den Status auf „In Korrektur" oder „Korrektur durchgeführt" setzen.
            </div>
        @elseif ($istSpruch)
            <div class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 text-sm text-indigo-900">
                Dies ist der <strong>klassenweite Zeugnisspruch</strong> als gemeinsame Arbeitshilfe/Vorlage. Auf dem Zeugnis
                erscheint der <strong>individuelle Schüler-Spruch</strong> (Variable <code>{Zeugnisspruch}</code>) – dieser
                klassenweite Text wird nicht direkt gedruckt.
            </div>
        @else
            <div class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 text-sm text-indigo-900">
                Dieser Text gilt <strong>klassenweit</strong> für {{ $fach?->name ?? 'den Haupttext' }} und erscheint auf jedem
                Zeugnis dieser Klasse <strong>vor</strong> dem jeweiligen Schülertext.
            </div>
        @endif

        @if ($ohneBereiche)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Für diese Klasse sind noch <strong>keine eigenen Fachbereiche</strong> angelegt (nur der Standard „Allgemein") – das Hauptzeugnis kann aus mehreren benannten Fachbereichen (z. B. Rechnen, Formenzeichnen …) bestehen.
                @if ($kannBereiche)
                    <a href="{{ route('module.schulzeugnis.klassen.edit', $klasse) }}#fachbereiche"
                       class="font-medium text-amber-800 underline hover:no-underline">Jetzt Fachbereiche anlegen</a>.
                @else
                    Bitte die Zeugnisverwaltung bitten, Fachbereiche für diese Klasse anzulegen.
                @endif
            </div>
        @endif

        <div class="zt-cols">
        <div class="zt-main space-y-4">
        <form method="POST"
              action="{{ route('module.schulzeugnis.klassenraeume.klassentexte.update', ['klasse' => $klasse, 'fach' => $fachParam]) }}"
              class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            @csrf
            @method('PUT')

            <div>
                <label for="text" class="block text-sm font-medium text-gray-700">Klassenweiter Text</label>
                <textarea name="text" id="text" rows="12"
                          class="zt-txt-area mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="Text, der für alle Schüler dieser Klasse gilt …">{{ old('text', $klassentext->text) }}</textarea>
                @error('text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @if (in_array($berechtigung, ['voll', 'korrektor']))
                <label class="block text-sm font-medium text-gray-700">Notiz <span class="text-gray-400">(intern, erscheint nicht auf dem Zeugnis)</span>
                    <textarea name="notiz" rows="2"
                              class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="z. B. Rückfrage, Erinnerung …">{{ old('notiz', $klassentext->notiz) }}</textarea>
                </label>
            @endif

            @php
                $statusOptionen = $berechtigung === 'korrektor' ? collect($stati)->only($korrekturStati) : $stati;
                $statusFarbe = ['gray' => '#9ca3af', 'amber' => '#f59e0b', 'red' => '#ef4444', 'green' => '#16a34a'];
                $aktStatus = old('status', $klassentext->status ?? 'unbearbeitet');
                $aktMeta = $stati[$aktStatus] ?? ['label' => '—', 'icon' => 'bx-circle', 'farbe' => 'gray'];
            @endphp
            <div class="zt-two">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Bearbeitungsstatus</label>
                    <div class="zt-status" id="zt-status">
                        <input type="hidden" name="status" value="{{ $aktStatus }}">
                        <button type="button" class="zt-status-btn">
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
                    <p class="mt-1 text-xs text-gray-400">Erscheint in der „Klassenweit"-Zeile der Zeugnis-Tabelle.</p>
                </div>

                @if ($berechtigung === 'voll')
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Korrektoren <span class="text-gray-400">(dürfen diesen Text korrigieren)</span></label>
                        <div id="zt-korr" class="zt-korr">
                            <div class="zt-korr-box">
                                <input type="text" class="zt-korr-input" placeholder="Lehrer suchen …" autocomplete="off">
                            </div>
                            <ul class="zt-korr-list" hidden></ul>
                        </div>
                        <script type="application/json" id="zt-korr-data">@json($alleLehrer->map(fn ($l) => ['id' => $l->id, 'name' => $l->fullName()])->values())</script>
                        <script type="application/json" id="zt-korr-selected">@json(collect(old('korrektoren', $korrektorIds))->map(fn ($v) => (int) $v)->values())</script>
                        <p class="mt-1 text-xs text-gray-400">Tippen zum Suchen, Klick zum Hinzufügen. Pflicht, wenn du den Status auf „Frei zur Korrektur" oder „Korrektur nötig" setzt.</p>
                    </div>
                @endif
            </div>

            @php
                $istVoll = $berechtigung === 'voll';
                $urlPrev  = $navPrev ? route('module.schulzeugnis.klassenraeume.klassentexte.edit', ['klasse' => $klasse, 'fach' => $navPrev['param']]) : '';
                $urlNext  = $navNext ? route('module.schulzeugnis.klassenraeume.klassentexte.edit', ['klasse' => $klasse, 'fach' => $navNext['param']]) : '';
                $labelNext = $navNext ? 'Nächstes Fach: ' . $navNext['name'] : 'Nächstes Fach (keins)';
                $labelPrev = $navPrev ? 'Vorheriges Fach: ' . $navPrev['name'] : 'Vorheriges Fach (keins)';
                // Korrektoren dürfen nicht zu Nachbar-Fächern springen (dort nicht zugewiesen → 403);
                // sie kehren zu ihren ToDos zurück.
                $zurueckUrl   = $istVoll ? $indexUrl : route('module.schulzeugnis.todo.index');
                $zurueckLabel = $istVoll ? 'Zurück zur Zeugnis-Tabelle' : 'Zurück zu meinen ToDos';
                $vorauswahl = $istVoll ? ($navNext ? 'next' : ($navPrev ? 'prev' : 'index')) : 'index';
            @endphp
            <div class="space-y-3 border-t border-gray-100 pt-4" id="zt-nav">
                <p class="text-sm font-medium text-gray-700">Danach weiter zu:</p>
                <div class="space-y-1.5 text-sm">
                    @if ($istVoll)
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
                    @endif
                    <label class="flex items-center gap-2 text-gray-700">
                        <input type="radio" name="weiter" value="index" data-url="{{ $zurueckUrl }}"
                               @checked($vorauswahl === 'index')
                               class="text-indigo-600 focus:ring-indigo-500">
                        <i class="bx {{ $istVoll ? 'bx-table' : 'bx-list-check' }} text-lg text-indigo-500"></i>
                        {{ $zurueckLabel }}
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
                    @if ($istVoll && $navGesamt)
                        <span class="ml-auto text-xs text-gray-400">Fach {{ $navPosition }} von {{ $navGesamt }}</span>
                    @endif
                </div>
            </div>
        </form>

        {{-- Korrektor: Korrektur ablehnen (entfernt sich selbst, Status zurück auf „Frei zur Korrektur"). --}}
        @if ($berechtigung === 'korrektor')
            <form method="POST" action="{{ route('module.schulzeugnis.klassenraeume.klassentexte.ablehnen', ['klasse' => $klasse, 'fach' => $fachParam]) }}"
                  class="mt-4 rounded-xl border border-red-100 bg-red-50/40 p-4"
                  onsubmit="return confirm('Korrektur ablehnen? Du wirst als Korrektor entfernt und der Text geht als „Frei zur Korrektur“ zurück an die Lehrkraft.');">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-100">
                    <i class="bx bx-x-circle text-lg"></i> Korrektur ablehnen
                </button>
                <p class="mt-1 text-xs text-gray-500">Entfernt dich als Korrektor und setzt den Status zurück auf „Frei zur Korrektur" – die Lehrkraft wählt dann jemand anderen.</p>
            </form>
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
                                            @if ($berechtigung === 'voll' && $e['restorable']) data-restore="{{ $e['id'] }}" @endif>
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
                    @if ($berechtigung === 'voll')
                        <form id="zt-restore-form" method="POST"
                              action="{{ route('module.schulzeugnis.klassenraeume.klassentexte.wiederherstellen', ['klasse' => $klasse, 'fach' => $fachParam]) }}"
                              class="zt-modal-restore" hidden
                              onsubmit="return confirm('Den Vorher-Stand wiederherstellen? Der aktuelle Text wird dadurch ersetzt (bleibt im Verlauf).');">
                            @csrf
                            <input type="hidden" name="protokoll_id" id="zt-restore-id" value="">
                            <button type="submit"
                                    class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100">
                                <i class="bx bx-undo"></i> Diesen Vorher-Text wiederherstellen
                            </button>
                        </form>
                    @endif
                </div>
                <div class="zt-modal-col">
                    <div class="zt-modal-label">Nachher</div>
                    <div class="zt-modal-pre" id="zt-modal-neu"></div>
                </div>
            </div>
        </div>
    </div>

    @include('schulzeugnis::zeugnisse._editor-styles')
    @include('schulzeugnis::zeugnisse._editor-scripts')
</x-app-layout>
