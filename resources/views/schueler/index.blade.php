<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-module-icon name="user" class="text-2xl text-indigo-600" />
                <div>
                    <h1 class="text-xl font-semibold text-gray-800">Schüler</h1>
                    <p class="text-sm text-gray-500">Schuljahr {{ $schuljahr->name }} · {{ $schueler->count() }} Schüler</p>
                </div>
            </div>
            <a href="{{ route('module.schulzeugnis.schueler.create', $schuljahr) }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                + Neuer Schüler
            </a>
        </div>
    </x-slot>

    <div class="space-y-4">
        <a href="{{ route('module.schulzeugnis.schuljahre.index') }}"
           class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
            &larr; Zurück zu den Schuljahren
        </a>

        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ session('error') }}</div>
        @endif

        @if ($schueler->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                Noch kein Schüler in diesem Schuljahr. Lege den ersten an oder importiere die Klasse.
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-400">
                            <th class="px-4 py-2 font-medium">Nachname</th>
                            <th class="px-4 py-2 font-medium">Vorname</th>
                            <th class="px-4 py-2 font-medium">Klasse</th>
                            <th class="px-4 py-2 font-medium">Geburtsdatum</th>
                            <th class="px-4 py-2 font-medium">Geburtsort</th>
                            <th class="px-4 py-2 text-center font-medium">Geschl.</th>
                            <th class="px-4 py-2 font-medium">Externe ID</th>
                            <th class="px-4 py-2 text-right font-medium">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($schueler as $s)
                            <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50/60">
                                <td class="px-4 py-2">
                                    <a href="{{ route('module.schulzeugnis.schueler.edit', $s) }}"
                                       class="font-semibold text-indigo-600 hover:text-indigo-700 hover:underline" title="Schüler bearbeiten">{{ $s->nachname }}</a>
                                </td>
                                <td class="px-4 py-2 text-gray-700">{{ $s->vorname }}</td>
                                <td class="px-4 py-2">
                                    @if ($s->klasse)
                                        <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ $s->klasse->name }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-gray-600">{{ $s->geburtsdatum ? $s->geburtsdatum->format('d.m.Y') : '—' }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $s->geburtsort ?: '—' }}</td>
                                <td class="px-4 py-2 text-center text-gray-600">{{ $s->geschlecht ?: '—' }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $s->quell_id ?: '—' }}</td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                        <a href="{{ route('module.schulzeugnis.schueler.edit', $s) }}" class="text-indigo-600 hover:text-indigo-700">Bearbeiten</a>
                                        <span class="text-gray-300">·</span>
                                        <form method="POST" action="{{ route('module.schulzeugnis.schueler.destroy', $s) }}"
                                              onsubmit="return confirm('{{ $s->fullName() }} wirklich löschen?');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-700">Löschen</button>
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
