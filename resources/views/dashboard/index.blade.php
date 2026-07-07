<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="book" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Schulzeugnis</h1>
        </div>
    </x-slot>

    <div class="max-w-5xl space-y-6">
        <div class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-5">
            <p class="text-sm text-indigo-900">
                Modul-Gerüst steht. Zeugnis-Erstellung für die Waldorfschule –
                Textzeugnisse in den meisten Jahrgängen, Schulnoten 1–6 nur zum Abschluss.
                Die Bausteine unten werden Schritt für Schritt gebaut.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['Schuljahre', 'Anker – alles hängt am Schuljahr, Import additiv', 'calendar'],
                ['Klassen', 'Jahres-Klassen (z. B. 5a), je Schuljahr neu', 'users'],
                ['Schüler', 'je Schuljahr, mit Quell-ID – ohne User-Konto', 'user'],
                ['Lehrer', 'je Schuljahr, lose an Core-User-ID gekoppelt', 'user'],
                ['Fächer', 'feste Liste + Lehrauftrag je Klasse', 'list'],
                ['Zeugnisformate', 'Vorlagen: Text oder Noten, je Klasse/Schüler', 'category'],
                ['Zeugnisse', 'Haupttext + Fachtexte, Abschließen friert ein', 'book'],
            ] as [$titel, $text, $icon])
                <div class="rounded-xl border border-gray-200 bg-white p-5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <x-module-icon :name="$icon" class="text-lg text-gray-400" />
                            <h2 class="font-semibold text-gray-800">{{ $titel }}</h2>
                        </div>
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">in Aufbau</span>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">{{ $text }}</p>
                </div>
            @endforeach
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="font-semibold text-gray-800">Grundprinzipien (fest verdrahtet)</h2>
            <ul class="mt-2 space-y-1 text-sm text-gray-600 list-disc pl-5">
                <li>Schüler haben <strong>kein</strong> Login und keine Verbindung zur Core-Benutzertabelle.</li>
                <li>Das Modul ist eine <strong>Insel</strong>: keine Fremdschlüssel in den Core – Benutzer dürfen dort jederzeit gelöscht werden.</li>
                <li>Alte Schuljahre bleiben <strong>für immer</strong> vollständig einsehbar; Abschließen friert Inhalt und Autor ein.</li>
                <li>Ein <strong>append-only Protokoll</strong> hält jeden Schritt fest – wer, wann, was.</li>
            </ul>
        </div>
    </div>
</x-app-layout>
