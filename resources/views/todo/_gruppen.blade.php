{{-- Eine Bereichs-Liste als Klassen-Akkordeon: je Klasse eine farbige Kopfzeile
     (Stufenfarbe), eingeklappt; beim Öffnen erscheinen die nach Fach gruppierten
     Aufgaben inkl. Referenz auf die letzte Änderung (was/wann/wer).
     Erwartet: $gruppen, $farbeKlasse, $letzteAenderung. --}}
@php
    // Textfarbe je nach Helligkeit der Stufenfarbe (wie in der Zeugnisliste).
    $kontrast = function (string $hex): string {
        $h = ltrim($hex, '#');
        $r = hexdec(substr($h, 0, 2)); $g = hexdec(substr($h, 2, 2)); $b = hexdec(substr($h, 4, 2));
        return ((0.299 * $r + 0.587 * $g + 0.114 * $b) / 255) < 0.5 ? 'weiss' : 'schwarz';
    };
@endphp
<div class="todo-akkordeon space-y-3">
    @foreach ($gruppen as $gruppe)
        @php $klasse = $gruppe['klasse']; $farbe = $klasse->stufe?->farbe ?: '#64748b'; $ct = $kontrast($farbe); @endphp
        <div class="todo-klasse">
            <button type="button" class="todo-kopf todo-kopf-{{ $ct }}" style="--kr: {{ $farbe }}" aria-expanded="false">
                <i class="bx bx-chevron-right todo-chevron text-xl"></i>
                <span class="font-semibold">Klasse {{ $klasse->name }}</span>
                @if ($klasse->stufe)
                    <span class="todo-dim text-xs">{{ $klasse->stufe->name }}</span>
                @endif
                <span class="todo-badge ml-auto">{{ $gruppe['anzahl'] }} offen</span>
            </button>

            <div class="todo-inhalt" hidden>
                @foreach ($gruppe['faecher'] as $fach)
                    <div class="todo-fach">
                        <div class="mb-1 flex items-center gap-2">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $fach['label'] }}</span>
                            <span class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-500">{{ $fach['anzahl'] }}</span>
                        </div>

                        <ul class="space-y-0.5">
                            @foreach ($fach['items'] as $a)
                                @php
                                    $m   = $a->statusMeta();
                                    $s   = $a->zeugnis?->schueler;
                                    $log = $letzteAenderung->get($a->id);
                                @endphp
                                <li>
                                    <a href="{{ route('module.schulzeugnis.klassenraeume.abschnitte.edit', $a) }}"
                                       class="todo-zeile flex items-center justify-between gap-3 rounded-lg px-2 py-1.5">
                                        <div class="flex min-w-0 items-center gap-2">
                                            <i class="bx {{ $m['icon'] }} text-lg {{ $farbeKlasse[$m['farbe']] ?? 'text-gray-400' }}"></i>
                                            <div class="min-w-0">
                                                <div class="truncate text-sm text-gray-800">{{ $s?->nachname }}, {{ $s?->vorname }}</div>
                                                <div class="truncate text-xs text-gray-400">
                                                    @if ($log)
                                                        <i class="bx bx-history align-middle"></i>
                                                        {{ $log->beschreibung ?: 'geändert' }} ·
                                                        {{ $log->akteur_name ?: 'unbekannt' }} ·
                                                        {{ $log->created_at?->format('d.m.Y H:i') }} Uhr
                                                    @else
                                                        <i class="bx bx-minus align-middle"></i> noch nicht bearbeitet
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-3">
                                            <span class="text-xs text-gray-400">{{ $m['label'] }}</span>
                                            <span class="todo-oeffnen text-sm text-indigo-600">Öffnen &rarr;</span>
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
