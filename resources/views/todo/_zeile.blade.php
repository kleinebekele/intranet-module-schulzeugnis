{{-- Eine Aufgaben-Zeile. Erwartet $a (Abschnitt); erbt $farbeKlasse, $letzteAenderung. --}}
@php
    $m   = $a->statusMeta();
    $s   = $a->zeugnis?->schueler;
    $log = $letzteAenderung->get($a->id);
@endphp
<li>
    <a href="{{ route('module.schulzeugnis.klassenraeume.abschnitte.edit', $a) }}"
       class="todo-zeile flex items-center justify-between gap-3 rounded-lg px-2 py-1.5">
        <div class="flex min-w-0 items-center gap-2">
            <i class="bx {{ $m['icon'] }} text-lg {{ $farbeKlasse[$m['farbe']] ?? 'text-gray-400' }}"
               title="{{ $m['label'] }}"></i>
            <span class="todo-name truncate text-sm text-gray-800">{{ $s?->nachname }}, {{ $s?->vorname }}</span>
        </div>
        <div class="todo-verlauf shrink-0 truncate text-right text-xs text-gray-400">
            @if ($log)
                <i class="bx bx-history align-middle"></i>
                {{ $log->beschreibung ?: 'geändert' }} · {{ $log->akteur_name ?: 'unbekannt' }} · {{ $log->created_at?->format('d.m.Y H:i') }} Uhr
            @else
                <span class="text-gray-300">noch nicht bearbeitet</span>
            @endif
        </div>
    </a>
</li>
