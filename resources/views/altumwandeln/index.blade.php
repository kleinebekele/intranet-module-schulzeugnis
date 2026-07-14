<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="transfer" class="text-2xl text-indigo-600" />
            <div>
                <h1 class="text-xl font-semibold text-gray-800">Alte Zeugnisse umwandeln</h1>
                <p class="text-sm text-gray-500">PDFs aus dem alten Zeugnisprogramm druckfertig machen</p>
            </div>
        </div>
    </x-slot>

    {{--
        Ein Einstieg für beide Umwandlungen: erst das Format wählen, dann passt sich
        der Hinweis an und das Formular schickt an die zugehörige Route.
        Die Umwandlung selbst liegt weiterhin in AltZeugnisController /
        AltFachzeugnisController – hier ist nur die Auswahl davor.
    --}}
    <div class="max-w-xl space-y-4" x-data="{ art: 'zeugnisse' }">
        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ session('error') }}</div>
        @endif
        @error('pdf')
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ $message }}</div>
        @enderror

        {{-- Format-Auswahl --}}
        <fieldset class="rounded-xl border border-gray-200 bg-white p-4">
            <legend class="px-1 text-sm font-medium text-gray-700">Was möchtest du umwandeln?</legend>

            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                <label class="cursor-pointer rounded-lg border p-3 transition"
                       x-bind:class="art === 'zeugnisse'
                           ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500'
                           : 'border-gray-200 hover:bg-gray-50'">
                    <div class="flex items-start gap-2">
                        <input type="radio" name="art" value="zeugnisse" x-model="art" class="mt-1 text-indigo-600">
                        <div>
                            <span class="block text-sm font-medium text-gray-800">Zeugnisse</span>
                            <span class="block text-xs text-gray-500">je 4 A4-Seiten &rarr; A3-Broschüre</span>
                        </div>
                    </div>
                </label>

                <label class="cursor-pointer rounded-lg border p-3 transition"
                       x-bind:class="art === 'fachzeugnisse'
                           ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500'
                           : 'border-gray-200 hover:bg-gray-50'">
                    <div class="flex items-start gap-2">
                        <input type="radio" name="art" value="fachzeugnisse" x-model="art" class="mt-1 text-indigo-600">
                        <div>
                            <span class="block text-sm font-medium text-gray-800">Fachzeugnisse</span>
                            <span class="block text-xs text-gray-500">A4 duplex-tauglich machen</span>
                        </div>
                    </div>
                </label>
            </div>
        </fieldset>

        {{-- Hinweis zum gewählten Format --}}
        <div x-show="art === 'zeugnisse'" x-cloak
             class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 text-sm text-indigo-900">
            Lade die PDF aus dem alten Zeugnisprogramm hoch. Je <strong>4 aufeinanderfolgende A4-Seiten</strong>
            bilden ein Zeugnis. Die Ausgabe ist eine <strong>A3-Querformat-PDF</strong>, richtig angeordnet für den
            beidseitigen Druck und das Falzen zur Broschüre:
            <ul class="mt-2 list-disc pl-5">
                <li>Bogen 1: links = Seite 4, rechts = Seite 1</li>
                <li>Bogen 2: links = Seite 2, rechts = Seite 3</li>
            </ul>
            Die Seitenzahl der Original-PDF muss durch 4 teilbar sein.
        </div>

        <div x-show="art === 'fachzeugnisse'" x-cloak
             class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 text-sm text-indigo-900">
            Lade die PDF mit den alten Fachzeugnissen hoch (normale A4-Seiten, beidseitiger Druck).
            Jedes Fachzeugnis, das aus einer <strong>ungeraden</strong> Anzahl Seiten besteht, bekommt eine
            <strong>Leerseite</strong> angehängt. So beginnt beim beidseitigen Druck jedes neue Fachzeugnis
            wieder auf der Vorderseite eines frischen Blattes. Die Reihenfolge und das Format bleiben unverändert.
        </div>

        <form method="POST" enctype="multipart/form-data"
              x-bind:action="art === 'zeugnisse'
                  ? '{{ route('module.schulzeugnis.altumwandeln.zeugnisse') }}'
                  : '{{ route('module.schulzeugnis.altumwandeln.fachzeugnisse') }}'"
              action="{{ route('module.schulzeugnis.altumwandeln.zeugnisse') }}"
              class="space-y-4 rounded-xl border border-gray-200 bg-white p-6">
            @csrf

            <div>
                <label for="pdf" class="block text-sm font-medium text-gray-700">Original-PDF (A4)</label>
                <input type="file" name="pdf" id="pdf" accept="application/pdf,.pdf" required
                       class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-600 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-indigo-700">
                <p class="mt-1 text-xs text-gray-400">Max. 100 MB. Die Datei wird nur umgewandelt und nicht gespeichert.</p>
            </div>

            <button type="submit"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Umwandeln &amp; herunterladen
            </button>
        </form>
    </div>
</x-app-layout>
