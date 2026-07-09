{{-- Eine Bereichs-Liste: je Klasse eine Karte, darin nach Fach gruppierte Schüler.
     Erwartet: $gruppen (aus TodoController::gruppiere) und $farbeKlasse. --}}
@foreach ($gruppen as $gruppe)
    @php $klasse = $gruppe['klasse']; $farbe = $klasse->stufe?->farbe ?: '#64748b'; @endphp
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2.5" style="border-left: 4px solid {{ $farbe }}">
            <div class="flex items-center gap-2">
                <span class="inline-block h-3 w-3 rounded-sm ring-1 ring-black/10" style="background: {{ $farbe }}"></span>
                <h3 class="font-semibold text-gray-800">Klasse {{ $klasse->name }}</h3>
                @if ($klasse->stufe)
                    <span class="text-xs text-gray-400">{{ $klasse->stufe->name }}</span>
                @endif
            </div>
            <span class="text-xs font-medium text-gray-500">{{ $gruppe['anzahl'] }} offen</span>
        </div>

        <div class="divide-y divide-gray-100">
            @foreach ($gruppe['faecher'] as $fach)
                <div class="px-4 py-2.5">
                    <div class="mb-1.5 flex items-center gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $fach['label'] }}</span>
                        <span class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-500">{{ $fach['anzahl'] }}</span>
                    </div>

                    <ul class="space-y-0.5">
                        @foreach ($fach['items'] as $a)
                            @php
                                $m = $a->statusMeta();
                                $s = $a->zeugnis?->schueler;
                            @endphp
                            <li>
                                <a href="{{ route('module.schulzeugnis.abschnitte.edit', $a) }}"
                                   class="group flex items-center justify-between rounded-lg px-2 py-1.5 hover:bg-indigo-50/60">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <i class="bx {{ $m['icon'] }} text-lg {{ $farbeKlasse[$m['farbe']] ?? 'text-gray-400' }}"></i>
                                        <span class="truncate text-sm text-gray-800">{{ $s?->nachname }}, {{ $s?->vorname }}</span>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-3">
                                        <span class="text-xs text-gray-400">{{ $m['label'] }}</span>
                                        <span class="text-sm text-indigo-600 opacity-0 transition group-hover:opacity-100">Öffnen &rarr;</span>
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
