<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="category" class="text-2xl text-indigo-600" />
            <div>
                <h1 class="text-xl font-semibold text-gray-800">Stammdaten-Import</h1>
                <p class="text-sm text-gray-500">CSV aus dem Schulverwaltungsprogramm additiv übernehmen – mit Vorschau vor dem Speichern</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-4" x-data="{ art: 'faecher', quelle: '{{ $dateien ? 'storage' : 'upload' }}', schuljahrArten: @js($schuljahrArten) }">
        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ session('error') }}</div>
        @endif

        <div class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 text-sm text-indigo-900">
            Der Import ist <strong>additiv</strong>: Es wird nur angelegt oder aktualisiert – <strong>nie gelöscht</strong>.
            Nach dem Auswählen siehst du zuerst eine <strong>Vorschau (Trockenlauf)</strong>; erst nach dem Bestätigen
            wird geschrieben. Ein erneuter Import derselben Datei erzeugt keine Duplikate.
        </div>

        <form method="POST" action="{{ route('module.schulzeugnis.import.vorschau') }}"
              enctype="multipart/form-data" class="grid gap-4 lg:grid-cols-2">
            @csrf

            {{-- Linke Spalte: Was & Woher --}}
            <div class="space-y-4 rounded-xl border border-gray-200 bg-white p-6">
                <div>
                    <label for="art" class="block text-sm font-medium text-gray-700">Was importieren?</label>
                    <select name="art" id="art" x-model="art"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($arten as $key => $meta)
                            <option value="{{ $key }}">{{ $meta['titel'] }}</option>
                        @endforeach
                    </select>
                    @foreach ($arten as $key => $meta)
                        <p class="mt-1 text-xs text-gray-500" x-show="art === '{{ $key }}'">{{ $meta['hinweis'] }}</p>
                    @endforeach
                </div>

                <div x-show="schuljahrArten.includes(art)" x-cloak>
                    <label for="schuljahr_id" class="block text-sm font-medium text-gray-700">Ziel-Schuljahr</label>
                    <select name="schuljahr_id" id="schuljahr_id"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($schuljahre as $sj)
                            <option value="{{ $sj->id }}" @selected($sj->id === $aktivId)>{{ $sj->name }}@if ($sj->is_active) (aktiv)@endif</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Lehrer werden in dieses Schuljahr importiert (additiv).</p>
                </div>

                <div>
                    <span class="block text-sm font-medium text-gray-700">Woher kommt die Datei?</span>
                    <div class="mt-2 space-y-3">
                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            <input type="radio" name="quelle" value="storage" x-model="quelle"
                                   @if (! $dateien) disabled @endif
                                   class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                            <span>
                                Aus dem Import-Ordner
                                <span class="block text-xs text-gray-400">Per SFTP/SSH abgelegt unter <code class="rounded bg-gray-100 px-1">{{ $ordner }}</code></span>
                            </span>
                        </label>

                        <div class="pl-6" x-show="quelle === 'storage'" x-cloak>
                            @if ($dateien)
                                <select name="storage_datei"
                                        class="block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach ($dateien as $datei)
                                        <option value="{{ $datei }}">{{ $datei }}</option>
                                    @endforeach
                                </select>
                            @else
                                <p class="text-xs text-gray-400">Noch keine CSV-Datei im Ordner.</p>
                            @endif
                        </div>

                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            <input type="radio" name="quelle" value="upload" x-model="quelle"
                                   class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                            <span>Vom Computer hochladen</span>
                        </label>

                        <div class="pl-6" x-show="quelle === 'upload'" x-cloak>
                            <input type="file" name="datei" accept=".csv,.txt,text/csv,text/plain"
                                   class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-600 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-indigo-700">
                            <p class="mt-1 text-xs text-gray-400">CSV, max. 10 MB. Wird nur für den Import verarbeitet.</p>
                        </div>
                    </div>
                    @error('datei')
                        <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Vorschau erstellen
                </button>
            </div>

            {{-- Rechte Spalte: erwartetes Format je Art --}}
            <div class="space-y-4">
                @foreach ($arten as $key => $meta)
                    <div x-show="art === '{{ $key }}'" x-cloak class="rounded-xl border border-gray-200 bg-white p-6">
                        <h2 class="text-sm font-semibold text-gray-800">Erwartete Spalten – {{ $meta['titel'] }}</h2>
                        <p class="mt-1 text-xs text-gray-500">Kopfzeile mit diesen Spaltennamen (Reihenfolge und Groß-/Kleinschreibung egal), Semikolon-getrennt, UTF-8.</p>
                        <div class="mt-3 overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-400">
                                        <th class="py-1 pr-3 font-medium">Spalte</th>
                                        <th class="py-1 pr-3 font-medium">Pflicht</th>
                                        <th class="py-1 font-medium">Bedeutung</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($meta['spalten'] as $spalte)
                                        <tr class="border-b border-gray-100">
                                            <td class="py-1.5 pr-3"><code class="rounded bg-gray-100 px-1 text-gray-800">{{ $spalte['name'] }}</code></td>
                                            <td class="py-1.5 pr-3">
                                                @if ($spalte['pflicht'])
                                                    <span class="font-medium text-red-600">ja</span>
                                                @else
                                                    <span class="text-gray-400">optional</span>
                                                @endif
                                            </td>
                                            <td class="py-1.5 text-gray-600">{{ $spalte['info'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <p class="mt-4 text-xs font-medium text-gray-500">Beispiel</p>
                        <pre class="mt-1 overflow-x-auto rounded-lg bg-gray-900 p-3 text-xs leading-relaxed text-gray-100">{{ $meta['beispiel'] }}</pre>
                    </div>
                @endforeach
            </div>
        </form>
    </div>
</x-app-layout>
