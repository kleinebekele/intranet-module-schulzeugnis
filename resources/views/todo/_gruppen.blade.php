{{-- Zweistufige Aufgaben-Liste. Die oberste Ebene ist eine sichtbare Kopfzeile,
     die zweite Ebene ein Akkordeon. Je nach Gruppierung ist die farbige Ebene die
     Klasse (Stufenfarbe, weiße Schrift) und die neutrale Ebene das Fach.
     Erwartet: $gruppen (Baum aus TodoController::gruppiere), $farbeKlasse. --}}
<div class="todo-grid">
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
                            @if ($kind['offen']->isEmpty() && $kind['erledigtAnzahl'] > 0)
                                <p class="px-2 pb-1 text-xs text-gray-400">Keine offenen – nur erledigte.</p>
                            @endif

                            <ul class="space-y-0.5">
                                @foreach ($kind['offen'] as $a)
                                    @include('schulzeugnis::todo._zeile')
                                @endforeach
                            </ul>

                            @if ($kind['erledigtAnzahl'] > 0)
                                <label class="mt-1.5 inline-flex cursor-pointer items-center gap-1.5 px-2 text-xs text-gray-500 hover:text-gray-700">
                                    <input type="checkbox" class="todo-erledigt-toggle h-3.5 w-3.5 rounded border-gray-300 text-green-600 focus:ring-green-500">
                                    <i class="bx bxs-check-circle text-green-500"></i>
                                    Erledigte anzeigen ({{ $kind['erledigtAnzahl'] }})
                                </label>
                                <ul class="todo-erledigt-liste mt-1 space-y-0.5" hidden>
                                    @foreach ($kind['erledigt'] as $a)
                                        @include('schulzeugnis::todo._zeile')
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
