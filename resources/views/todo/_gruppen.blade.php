{{-- Zweistufige Aufgaben-Liste. Die oberste Ebene ist eine sichtbare Kopfzeile,
     die zweite Ebene ein Akkordeon. Je nach Gruppierung ist die farbige Ebene die
     Klasse (Stufenfarbe, weiße Schrift) und die neutrale Ebene das Fach.
     Erwartet: $gruppen (Baum aus TodoController::gruppiere), $farbeKlasse, $letzteAenderung. --}}
<div class="space-y-3">
    @foreach ($gruppen as $node)
        <div class="todo-node">
            {{-- Oberste Ebene: Kopfzeile (immer sichtbar) --}}
            @if ($node['farbe'])
                <div class="todo-head todo-farbe" style="--kr: {{ $node['farbe'] }}">
                    <span class="font-semibold">{{ $node['label'] }}</span>
                    @if ($node['sub'])
                        <span class="todo-dim text-xs">{{ $node['sub'] }}</span>
                    @endif
                    <span class="todo-badge ml-auto">{{ $node['anzahl'] }} offen</span>
                </div>
            @else
                <div class="todo-head todo-neutral-head">
                    <i class="bx bx-bookmark text-lg"></i>
                    <span class="font-semibold">{{ $node['label'] }}</span>
                    <span class="todo-badge ml-auto">{{ $node['anzahl'] }} offen</span>
                </div>
            @endif

            {{-- Zweite Ebene: Akkordeon --}}
            <div class="todo-kinder">
                @foreach ($node['kinder'] as $kind)
                    <div class="todo-kind">
                        <button type="button"
                                class="todo-akk {{ $kind['farbe'] ? 'todo-farbe todo-akk-farbe' : 'todo-akk-neutral' }}"
                                @if ($kind['farbe']) style="--kr: {{ $kind['farbe'] }}" @endif
                                aria-expanded="false">
                            <i class="bx bx-chevron-right todo-chevron text-lg"></i>
                            <span class="text-sm font-medium">{{ $kind['label'] }}</span>
                            @if ($kind['sub'])
                                <span class="todo-dim text-xs">{{ $kind['sub'] }}</span>
                            @endif
                            <span class="todo-badge ml-auto">{{ $kind['anzahl'] }} offen</span>
                        </button>

                        <div class="todo-inhalt" hidden>
                            <ul class="space-y-0.5">
                                @foreach ($kind['items'] as $a)
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
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
