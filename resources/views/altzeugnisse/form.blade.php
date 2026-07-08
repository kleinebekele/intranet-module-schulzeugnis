<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="category" class="text-2xl text-indigo-600" />
            <div>
                <h1 class="text-xl font-semibold text-gray-800">Alte Zeugnisse umwandeln</h1>
                <p class="text-sm text-gray-500">A4-PDF (je 4 Seiten = ein Zeugnis) → A3-Broschüre zum Falzen</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-xl space-y-4">
        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ session('error') }}</div>
        @endif
        @error('pdf')
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ $message }}</div>
        @enderror

        <div class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 text-sm text-indigo-900">
            Lade die PDF aus dem alten Zeugnisprogramm hoch. Je <strong>4 aufeinanderfolgende A4-Seiten</strong>
            bilden ein Zeugnis. Die Ausgabe ist eine <strong>A3-Querformat-PDF</strong>, richtig angeordnet für den
            beidseitigen Druck und das Falzen zur Broschüre:
            <ul class="mt-2 list-disc pl-5">
                <li>Bogen 1: links = Seite 4, rechts = Seite 1</li>
                <li>Bogen 2: links = Seite 2, rechts = Seite 3</li>
            </ul>
            Die Seitenzahl der Original-PDF muss durch 4 teilbar sein.
        </div>

        <form method="POST" action="{{ route('module.schulzeugnis.altzeugnisse.umwandeln') }}"
              enctype="multipart/form-data" class="space-y-4 rounded-xl border border-gray-200 bg-white p-6">
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
