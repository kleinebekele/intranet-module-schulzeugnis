{{-- Eine Aufgaben-Zeile. Erwartet $a (Abschnitt); erbt $farbeKlasse. --}}
@php
    $m = $a->statusMeta();
    $s = $a->zeugnis?->schueler;
@endphp
<li>
    <a href="{{ route('module.schulzeugnis.klassenraeume.abschnitte.edit', ['abschnitt' => $a, 'quelle' => 'todo']) }}"
       data-ab="{{ $a->id }}"
       class="todo-zeile flex items-center gap-2 rounded-lg px-2 py-1.5">
        <i class="bx {{ $m['icon'] }} text-lg {{ $farbeKlasse[$m['farbe']] ?? 'text-gray-400' }}"
           title="{{ $m['label'] }}"></i>
        <span class="todo-name truncate text-sm text-gray-800">{{ $s?->nachname }}, {{ $s?->vorname }}</span>
    </a>
</li>
