<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="category" class="text-2xl text-indigo-600" />
            <div>
                <h1 class="text-xl font-semibold text-gray-800">Umwandlung fertig</h1>
                <p class="text-sm text-gray-500">{{ $zeugnisse }} Zeugnisse · {{ $seiten }} A4-Seiten → A3-Broschüre</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-xl space-y-4">
        <a href="{{ route('module.schulzeugnis.altzeugnisse.form') }}"
           class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
            &larr; Neue Datei umwandeln
        </a>

        {{-- Raute-Prüfung --}}
        @if (! $rauten['ok'])
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Hinweis: Die Textprüfung auf Rauten war nicht möglich (die PDF ließ sich nicht als Text lesen).
                Die Umwandlung selbst hat funktioniert. <span class="text-amber-700">({{ \Illuminate\Support\Str::limit($rauten['fehler'], 120) }})</span>
            </div>
        @elseif (! empty($rauten['treffer']))
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <p class="font-semibold">⚠ Raute „#" gefunden – bitte diese Zeugnisse prüfen:</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($rauten['treffer'] as $t)
                        <li>
                            <strong>{{ $t['name'] ?: 'Zeugnis ' . $t['zeugnis'] }}</strong>
                            <span class="text-red-700">(Zeugnis {{ $t['zeugnis'] }}, Original-Seite{{ count($t['seiten']) > 1 ? 'n' : '' }} {{ implode(', ', $t['seiten']) }})</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 text-red-700">Eine „#" steht im alten Programm meist für abgeschnittene oder fehlende Feld-Inhalte.</p>
            </div>
        @else
            <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                ✓ Keine Rauten „#" im Text gefunden.
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <a href="{{ route('module.schulzeugnis.altzeugnisse.download', ['token' => $token, 'name' => $ausgabeName]) }}"
               class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <i class="bx bxs-file-pdf text-lg"></i> A3-Broschüre herunterladen
            </a>
            <p class="mt-2 text-xs text-gray-500">Dateiname: <span class="font-medium text-gray-700">{{ $ausgabeName }}</span></p>
            <p class="mt-1 text-xs text-gray-400">Der Download-Link ist temporär (ca. 1 Stunde gültig).</p>
        </div>
    </div>
</x-app-layout>
