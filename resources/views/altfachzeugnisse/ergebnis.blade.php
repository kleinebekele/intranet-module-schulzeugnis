<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="category" class="text-2xl text-indigo-600" />
            <div>
                <h1 class="text-xl font-semibold text-gray-800">Umwandlung fertig</h1>
                <p class="text-sm text-gray-500">{{ $fachzeugnisse }} Fachzeugnisse · {{ $seiten }} A4-Seiten · duplex-tauglich</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-xl space-y-4">
        <a href="{{ route('module.schulzeugnis.altfachzeugnisse.form') }}"
           class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
            &larr; Neue Datei umwandeln
        </a>

        {{-- Ergänzte Leerseiten (Fachzeugnisse mit ungerader Seitenzahl) --}}
        @if (! empty($ergaenzt))
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <p class="font-semibold">Bei folgenden Fachzeugnissen wurde eine Leerseite ergänzt (ungerade Seitenzahl):</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($ergaenzt as $e)
                        <li>
                            <strong>{{ $e['name'] ?: 'unbekannt' }}</strong> –
                            {{ $e['anzahl'] }} Seite{{ $e['anzahl'] == 1 ? '' : 'n' }} → 1 Leerseite angehängt
                            <span class="text-amber-700">(ab Original-Seite {{ $e['startSeite'] }})</span>.
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                ✓ Alle Fachzeugnisse hatten bereits eine gerade Seitenzahl – nichts zu ergänzen.
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <a href="{{ route('module.schulzeugnis.altfachzeugnisse.download', ['token' => $token, 'name' => $ausgabeName]) }}"
               class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <i class="bx bxs-file-pdf text-lg"></i> Duplex-PDF herunterladen
            </a>
            <p class="mt-2 text-xs text-gray-500">Dateiname: <span class="font-medium text-gray-700">{{ $ausgabeName }}</span></p>
            <p class="mt-1 text-xs text-gray-400">Der Download-Link ist temporär (ca. 1 Stunde gültig).</p>
        </div>
    </div>
</x-app-layout>
