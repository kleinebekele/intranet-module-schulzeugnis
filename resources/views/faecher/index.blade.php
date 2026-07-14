<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-module-icon name="list" class="text-2xl text-indigo-600" />
                <h1 class="text-xl font-semibold text-gray-800">Fächer</h1>
            </div>
            <a href="{{ route('module.schulzeugnis.faecher.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                + Neues Fach
            </a>
        </div>
    </x-slot>

    <div class="space-y-3">
        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ session('error') }}</div>
        @endif

        @if ($faecher->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                Noch kein Fach angelegt. Lege die feste Fächerliste an (Deutsch, Eurythmie, …).
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-400">
                            <th class="px-4 py-2 font-medium">Reihenfolge</th>
                            <th class="px-4 py-2 font-medium">Name</th>
                            <th class="px-4 py-2 font-medium">Kürzel</th>
                            <th class="px-4 py-2 text-center font-medium">Status</th>
                            <th class="px-4 py-2 text-right font-medium">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($faecher as $fach)
                            <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50/60 {{ $fach->aktiv ? '' : 'opacity-60' }}">
                                <td class="px-4 py-2 text-gray-400">{{ $fach->reihenfolge }}</td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('module.schulzeugnis.faecher.edit', $fach) }}"
                                       class="font-semibold text-indigo-600 hover:text-indigo-700 hover:underline" title="Fach bearbeiten">{{ $fach->name }}</a>
                                </td>
                                <td class="px-4 py-2 text-gray-600">{{ $fach->kuerzel ?: '—' }}</td>
                                <td class="px-4 py-2 text-center">
                                    @if ($fach->aktiv)
                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">aktiv</span>
                                    @else
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">archiviert</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                        <form method="POST" action="{{ route('module.schulzeugnis.faecher.toggle', $fach) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-indigo-600 hover:text-indigo-700">{{ $fach->aktiv ? 'Archivieren' : 'Reaktivieren' }}</button>
                                        </form>
                                        <span class="text-gray-300">·</span>
                                        <form method="POST" action="{{ route('module.schulzeugnis.faecher.destroy', $fach) }}"
                                              onsubmit="return confirm('Fach {{ $fach->name }} wirklich löschen?');" class="inline">
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
