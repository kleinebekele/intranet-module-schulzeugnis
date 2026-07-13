{{-- Klassenweite Texte, die dieser Korrektor korrigieren soll (klassen-, nicht
     schülerbezogen). Erwartet: $ktGruppen (je Klasse label/farbe/sub/items), $farbeKlasse. --}}
<div class="mb-4">
    <p class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-indigo-500">
        <i class="bx bx-group text-sm"></i> Klassenweite Texte
    </p>
    <div class="todo-grid">
        @foreach ($ktGruppen as $g)
            <div class="todo-node">
                <div class="todo-head todo-farbe" style="--kr: {{ $g['farbe'] }}">
                    <span class="font-semibold">{{ $g['label'] }}</span>
                    @if ($g['sub'])
                        <span class="todo-dim text-xs">{{ $g['sub'] }}</span>
                    @endif
                    <span class="todo-badge ml-auto">{{ count($g['items']) }} offen</span>
                </div>
                <ul class="todo-kinder">
                    @foreach ($g['items'] as $it)
                        <li class="todo-kind">
                            <a href="{{ $it['url'] }}"
                               class="todo-zeile flex items-center gap-2 px-3 py-2">
                                <i class="bx {{ $it['status']['icon'] }} text-lg {{ $farbeKlasse[$it['status']['farbe']] ?? 'text-gray-400' }}"
                                   title="{{ $it['status']['label'] }}"></i>
                                <span class="todo-name truncate text-sm text-gray-800">{{ $it['fach'] }}</span>
                                <span class="ml-auto shrink-0 text-xs text-gray-400">{{ $it['status']['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</div>
