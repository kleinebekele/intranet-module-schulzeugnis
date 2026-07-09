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
            <x-module-icon name="edit" class="text-2xl text-indigo-600" />
            <div>
                <h1 class="text-xl font-semibold text-gray-800">Meine Korrekturen</h1>
                <p class="text-sm text-gray-500">Texte, die dir zur Korrektur zugewiesen sind</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-3xl space-y-3">
        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ session('error') }}</div>
        @endif

        @forelse ($abschnitte as $a)
            @php
                $m = $a->statusMeta();
                $schueler = $a->zeugnis?->schueler;
                $titel = $a->typ === 'haupttext' ? 'Haupttext' : ($a->fach?->name ?? 'Fachtext');
                $abgeschlossen = $a->zeugnis?->istAbgeschlossen();
            @endphp
            <a href="{{ route('module.schulzeugnis.klassenraeume.abschnitte.edit', $a) }}"
               class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 hover:bg-indigo-50/40">
                <div class="flex items-center gap-3">
                    <i class="bx {{ $m['icon'] }} text-xl {{ $farbeKlasse[$m['farbe']] ?? 'text-gray-300' }}"></i>
                    <div>
                        <p class="font-medium text-gray-800">{{ $titel }} &middot; {{ $schueler?->nachname }}, {{ $schueler?->vorname }}</p>
                        <p class="text-xs text-gray-500">
                            Klasse {{ $schueler?->klasse?->name ?? '—' }} &middot; {{ $schueler?->klasse?->schuljahr?->name ?? '' }}
                            &middot; Status: {{ $m['label'] }}
                            @if ($abgeschlossen) &middot; <span class="text-green-700">abgeschlossen</span> @endif
                        </p>
                    </div>
                </div>
                <span class="text-sm text-indigo-600">Öffnen &rarr;</span>
            </a>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                Aktuell sind dir keine Texte zur Korrektur zugewiesen.
            </div>
        @endforelse
    </div>
</x-app-layout>
