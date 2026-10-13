<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <i class="bx bx-import text-2xl text-indigo-600"></i>
            <div>
                <h1 class="text-xl font-semibold text-gray-800">Aus Linear – {{ $meta['titel'] }}</h1>
                <p class="text-sm text-gray-500">
                    Linear-Zeitraum: {{ $zeitraumName }} · Schuljahr {{ $schuljahr->name }} · Trockenlauf, es wurde noch nichts gespeichert
                </p>
            </div>
        </div>
    </x-slot>

    @php
        $zaehl = $analyse['zaehl'];
        $neu   = $zaehl['neu'];
        $akt   = $zaehl['aktualisiert'];
    @endphp

    <div class="space-y-4">
        <a href="{{ $zurueckUrl }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
            &larr; Zurück zu {{ $meta['titel'] }}
        </a>

        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ session('error') }}</div>
        @endif

        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <strong>Trockenlauf.</strong> So sähe der Stand nach dem Import aus – es wurde <strong>nichts geändert</strong>.
            Gelöscht wird beim Import nie etwas: Was hier fehlt, aber in Linear nicht (mehr) steht, bleibt wie es ist.
            <span class="mt-1 block text-amber-800">Die Importe bauen aufeinander auf: {{ $reihenfolge }}.</span>
            @if ($verknuepfen)
                <span class="mt-1 block text-amber-800">Das Schuljahr ist noch nicht mit Linear verknüpft – beim Import wird
                    „{{ $zeitraumName }}" als sein Linear-Zeitraum gespeichert.</span>
            @endif
        </div>

        @if (! empty($ohneId))
            <div class="rounded-xl border border-indigo-200 bg-indigo-50/60 p-4 text-sm">
                <p class="font-medium text-indigo-800"><i class="bx bx-link"></i> Ohne Linear-ID – mit Linear verknüpfen</p>
                <p class="mt-1 text-xs text-indigo-700">Meist von Hand angelegt und anders geschrieben als in Linear. Nach dem Verknüpfen erkennt der Import den Eintrag wieder.</p>
                <div class="mt-3 space-y-3">
                    @foreach ($ohneId as $p)
                        <div class="rounded-lg border border-indigo-100 bg-white p-3">
                            <p class="font-medium text-gray-800">{{ $p['name'] }}</p>
                            @if (empty($p['kandidaten']))
                                <p class="mt-1 text-xs text-gray-500">In Linear kein ähnlicher Name gefunden. Linear-ID unter Bearbeiten von Hand eintragen.</p>
                            @else
                                <ul class="mt-1 space-y-1">
                                    @foreach ($p['kandidaten'] as $k)
                                        <li class="flex flex-wrap items-center gap-2">
                                            <span class="text-gray-700">{{ trim($k['vorname'].' '.$k['nachname']) }}</span>
                                            <span class="text-xs text-gray-400">Linear-ID {{ $k['adrnr'] }}</span>
                                            @if ($k['vergeben'])
                                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">schon an einem anderen Eintrag – dann ist dieser hier wohl doppelt</span>
                                            @else
                                                <form method="POST" action="{{ $p['url'] }}" class="inline"
                                                      data-bestaetigen="{{ $p['name'] }} mit Linear-ID {{ $k['adrnr'] }} ({{ trim($k['vorname'].' '.$k['nachname']) }}) verknüpfen?" data-knopf="Verknüpfen">
                                                    @csrf
                                                    <input type="hidden" name="adrnr" value="{{ $k['adrnr'] }}">
                                                    <button type="submit" class="rounded-md border border-indigo-300 bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100">Mit dieser Linear-ID verknüpfen</button>
                                                </form>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @include('schulzeugnis::import._tabelle', ['analyse' => $analyse, 'nurImportierte' => false, 'zeilenAktion' => ['url' => $formUrl, 'zeitraum' => $zid]])

        <div class="rounded-xl border border-gray-200 bg-white p-6">
            @if ($neu + $akt > 0)
                <form method="POST" action="{{ $formUrl }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="zeitraum" value="{{ $zid }}">

                    <p class="text-sm text-gray-700">
                        Linear kennt <strong>{{ $neu }}</strong> {{ $neu === 1 ? 'Eintrag' : 'Einträge' }}, die hier noch fehlen,
                        und <strong>{{ $akt }}</strong> mit abweichenden Angaben. Einzelne Zeilen lassen sich auch direkt in der Tabelle übernehmen.
                        @if ($zaehl['fehler'] > 0 || $zaehl['warnung'] > 0)
                            <span class="text-amber-700">({{ $zaehl['warnung'] }} übersprungen, {{ $zaehl['fehler'] }} mit Fehler werden ignoriert.)</span>
                        @endif
                    </p>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="submit"
                                class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                            Alle Änderungen übernehmen ({{ $neu + $akt }})
                        </button>
                        <a href="{{ $zurueckUrl }}" class="ml-1 text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
                    </div>
                </form>
            @else
                <p class="text-sm text-gray-600">
                    Alles stimmt mit Linear überein – es gibt <strong>nichts zu importieren</strong>.
                </p>
                <a href="{{ $zurueckUrl }}"
                   class="mt-3 inline-block rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Zurück
                </a>
            @endif
        </div>
    </div>
</x-app-layout>
