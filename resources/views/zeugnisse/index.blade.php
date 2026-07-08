@php
    $farbeKlasse = [
        'gray'  => 'text-gray-300',
        'amber' => 'text-amber-500',
        'red'   => 'text-red-500',
        'green' => 'text-green-600',
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="book" class="text-2xl text-indigo-600" />
            <div>
                <h1 class="text-xl font-semibold text-gray-800">Zeugnisse</h1>
                <p class="text-sm text-gray-500">Klasse {{ $klasse->name }} &middot; Schuljahr {{ $klasse->schuljahr->name }} &middot; {{ $schueler->count() }} Schüler</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-4">
        <a href="{{ route('module.schulzeugnis.klassen.index', $klasse->schuljahr_id) }}"
           class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
            &larr; Zurück zu den Klassen
        </a>

        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
                {{ session('error') }}
            </div>
        @endif

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
            <table class="min-w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 text-gray-600">
                        <th class="sticky left-0 z-10 bg-gray-50 px-4 py-2 text-left font-semibold">Schüler</th>
                        <th class="px-2 py-2 text-center font-semibold" title="Haupttext (Klassenlehrer)">Haupt</th>
                        @foreach ($faecher as $fach)
                            <th class="px-2 py-2 text-center font-semibold" title="{{ $fach->name }}">{{ $fach->kuerzel ?: $fach->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($schueler as $s)
                        @php
                            $z = $s->zeugnis;
                            $abs = $z ? $z->abschnitte : collect();
                            $haupt = $abs->firstWhere('typ', 'haupttext');
                            $fachMap = $abs->whereIn('typ', ['fachtext', 'note'])->keyBy('fach_id');
                        @endphp
                        <tr class="border-b border-gray-100 hover:bg-indigo-50/40">
                            <td class="sticky left-0 z-10 bg-white px-4 py-2 hover:bg-indigo-50/40">
                                @if ($z)
                                    <a href="{{ route('module.schulzeugnis.zeugnisse.edit', $z) }}"
                                       class="font-medium text-indigo-700 hover:underline">{{ $s->nachname }}, {{ $s->vorname }}</a>
                                    @if ($z->istAbgeschlossen())
                                        <span class="ml-1 rounded-full bg-green-100 px-1.5 py-0.5 text-[10px] font-medium text-green-700">fertig</span>
                                    @endif
                                @else
                                    <span class="font-medium text-gray-700">{{ $s->nachname }}, {{ $s->vorname }}</span>
                                    <form method="POST" action="{{ route('module.schulzeugnis.zeugnisse.store', [$klasse, $s]) }}" class="mt-1">
                                        @csrf
                                        <button type="submit" class="text-xs text-indigo-600 hover:underline">Zeugnis anlegen</button>
                                    </form>
                                @endif
                            </td>

                            {{-- Haupttext-Status --}}
                            <td class="px-2 py-2 text-center">
                                @if ($haupt)
                                    @php $m = $haupt->statusMeta(); @endphp
                                    <i class="bx {{ $m['icon'] }} text-lg {{ $farbeKlasse[$m['farbe']] ?? 'text-gray-300' }}" title="Haupttext – {{ $m['label'] }}"></i>
                                @else
                                    <span class="text-gray-200">–</span>
                                @endif
                            </td>

                            {{-- Status je Fach --}}
                            @foreach ($faecher as $fach)
                                <td class="px-2 py-2 text-center">
                                    @php $a = $fachMap->get($fach->id); @endphp
                                    @if ($a)
                                        @php $m = $a->statusMeta(); @endphp
                                        <i class="bx {{ $m['icon'] }} text-lg {{ $farbeKlasse[$m['farbe']] ?? 'text-gray-300' }}" title="{{ $fach->name }} – {{ $m['label'] }}"></i>
                                    @else
                                        <span class="text-gray-200">–</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $faecher->count() + 2 }}" class="px-4 py-8 text-center text-gray-500">
                                Noch keine Schüler in dieser Klasse.
                                <a href="{{ route('module.schulzeugnis.schueler.index', $klasse->schuljahr_id) }}" class="text-indigo-600 hover:text-indigo-700">Schüler anlegen</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Legende der Bearbeitungs-Status --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Bearbeitungsstatus</p>
            <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1.5">
                @foreach ($stati as $meta)
                    <span class="inline-flex items-center gap-1.5 text-sm text-gray-600">
                        <i class="bx {{ $meta['icon'] }} text-lg {{ $farbeKlasse[$meta['farbe']] ?? 'text-gray-300' }}"></i>
                        {{ $meta['label'] }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
