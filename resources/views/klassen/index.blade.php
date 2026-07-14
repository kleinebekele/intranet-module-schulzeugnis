<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-module-icon name="users" class="text-2xl text-indigo-600" />
                <div>
                    <h1 class="text-xl font-semibold text-gray-800">Klassen</h1>
                    <p class="text-sm text-gray-500">Schuljahr {{ $schuljahr->name }}</p>
                </div>
            </div>
            <a href="{{ route('module.schulzeugnis.klassen.create', $schuljahr) }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                + Neue Klasse
            </a>
        </div>
    </x-slot>

    @php
        $jaNein = fn ($v) => $v
            ? '<span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">ja</span>'
            : '<span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">nein</span>';
    @endphp

    <div class="space-y-4">
        <a href="{{ route('module.schulzeugnis.schuljahre.index') }}"
           class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
            &larr; Zurück zu den Schuljahren
        </a>

        {{-- Erfolgsmeldungen rendert das Core-Layout global – hier nur Fehler. --}}
        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
                {{ session('error') }}
            </div>
        @endif

        @if ($klassen->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                Noch keine Klasse in diesem Schuljahr. Lege die erste an.
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-400">
                            <th class="px-4 py-2 font-medium">Name</th>
                            <th class="px-4 py-2 font-medium">Schulstufe</th>
                            <th class="px-4 py-2 font-medium">Klassenlehrer</th>
                            <th class="px-4 py-2 text-center font-medium">Fachzeugnis</th>
                            <th class="px-4 py-2 text-center font-medium">Hauptzeugnis</th>
                            <th class="px-4 py-2 text-center font-medium">Zeugnisspruch</th>
                            <th class="px-4 py-2 text-right font-medium">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($klassen as $klasse)
                            <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50/60">
                                <td class="px-4 py-2">
                                    <a href="{{ route('module.schulzeugnis.klassen.edit', $klasse) }}"
                                       class="font-semibold text-indigo-600 hover:text-indigo-700 hover:underline" title="Klasse bearbeiten">
                                        {{ $klasse->name }}
                                    </a>
                                </td>
                                <td class="px-4 py-2 text-gray-700">
                                    @if ($klasse->stufe)
                                        <span class="inline-flex items-center gap-1.5">
                                            <span class="inline-block h-2.5 w-2.5 rounded-full ring-1 ring-black/10" style="background: {{ $klasse->stufe->farbe }}"></span>
                                            {{ $klasse->stufe->name }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-gray-700">
                                    @if ($klasse->klassenlehrer)
                                        {{ $klasse->klassenlehrer->fullName() }}
                                    @else
                                        <span class="text-gray-400">nicht gesetzt</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-center">{!! $jaNein($klasse->hat_fachzeugnis) !!}</td>
                                <td class="px-4 py-2 text-center">
                                    @if ($klasse->hat_hauptzeugnis)
                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">ja ({{ $klasse->hauptbereiche_count }})</span>
                                    @else
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">nein</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-center">{!! $jaNein($klasse->hat_zeugnisspruch) !!}</td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                        <a href="{{ route('module.schulzeugnis.klassenraeume.zeugnisse.index', $klasse) }}"
                                           class="text-indigo-600 hover:text-indigo-700" title="Zeugnisse">Zeugnisse</a>
                                        <span class="text-gray-300">·</span>
                                        <a href="{{ route('module.schulzeugnis.klassen.lehrauftraege.index', $klasse) }}"
                                           class="text-indigo-600 hover:text-indigo-700" title="Lehraufträge">Lehraufträge ({{ $klasse->lehrauftraege_count }})</a>
                                        <span class="text-gray-300">·</span>
                                        <form method="POST" action="{{ route('module.schulzeugnis.klassen.destroy', $klasse) }}"
                                              onsubmit="return confirm('{{ $klasse->name }} wirklich löschen?');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-700" title="Klasse löschen">Löschen</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
