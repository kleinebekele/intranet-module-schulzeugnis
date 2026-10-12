{{-- Team-Teaching (mehrere Fachlehrer, siehe Support\Teamtext).
     Erwartet: $team (modus getrennt|zusammen, mein, andere), $readonly, $rows,
     $feld (Name des gemeinsamen Feldes: inhalt | text) und $gemeinsam (dessen Wert). --}}
@php
    $feld     = $feld ?? 'inhalt';
    $getrennt = $team['modus'] === 'getrennt';
    $mein     = $team['mein'];
    $kannSelbst = $mein && ! $readonly;
@endphp
<input type="hidden" name="team_modus" value="{{ $team['modus'] }}">

<div class="zt-team rounded-lg border border-indigo-200 bg-indigo-50/40 p-3">
    <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-indigo-600">
        <i class="bx bx-group text-sm"></i>
        {{ $getrennt ? 'Mehrere Fachlehrer – jeder schreibt zunächst seinen Teil' : 'Gemeinsamer Text aller Fachlehrer' }}
    </p>

    @if ($getrennt)
        @if ($mein)
            <label class="mt-2 block text-sm font-medium text-gray-700">Mein Text
                <textarea name="teiltext" rows="{{ $rows }}" data-fmt @disabled(! $kannSelbst)
                          class="zt-txt-area mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="Ihr Teil des Textes …">{{ old('teiltext', $mein->inhalt) }}</textarea>
            </label>
        @else
            <p class="mt-2 text-sm text-gray-600">Jede Lehrkraft schreibt ihren eigenen Teil. Bearbeiten lässt sich ein Teiltext nur von seiner Lehrkraft.</p>
        @endif

        @foreach ($team['andere'] as $t)
            <div class="zt-team-fremd mt-3 rounded-lg border border-gray-200 p-3">
                <p class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                    <span class="font-medium text-gray-600">Text von {{ $t->autor_name ?: 'Lehrkraft' }}</span>
                    @if ($t->zusammenfassen)
                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-green-800">möchte zusammenfassen</span>
                    @else
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-600">noch nicht zusammenfassen</span>
                    @endif
                </p>
                <div class="zt-team-text mt-1 text-sm">{{ trim((string) $t->inhalt) !== '' ? $t->inhalt : '— noch kein Text —' }}</div>
            </div>
        @endforeach
    @else
        <label class="mt-2 block text-sm font-medium text-gray-700">Gemeinsamer Text
            <textarea name="{{ $feld }}" rows="{{ $rows }}" data-fmt @disabled($readonly)
                      class="zt-txt-area mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      placeholder="Gemeinsamer Text …">{{ old($feld, $gemeinsam) }}</textarea>
        </label>
        <p class="mt-1 text-xs text-gray-500">Beteiligt: {{ collect([$mein])->filter()->concat($team['andere'])->pluck('autor_name')->filter()->implode(', ') }}</p>
    @endif

    @php
        // Andere haben schon zugestimmt, ich noch nicht → deutlich darauf hinweisen.
        $wartenAufMich = $getrennt && $kannSelbst && ! $mein->zusammenfassen
            ? $team['andere']->filter(fn ($t) => $t->zusammenfassen)->pluck('autor_name')->filter()
            : collect();
    @endphp
    @if ($wartenAufMich->isNotEmpty())
        <div class="zt-team-wartet mt-3 flex items-start gap-2 rounded-lg p-3 text-sm">
            <i class="bx bxs-bell-ring mt-0.5 text-lg"></i>
            <div>
                <strong>{{ $wartenAufMich->implode(', ') }} {{ $wartenAufMich->count() === 1 ? 'möchte' : 'möchten' }} die Texte zusammenfassen.</strong>
                <span class="block">Setzen Sie unten das Häkchen „Texte zusammenfassen“ und speichern Sie – dann entsteht ein gemeinsames Feld.</span>
            </div>
        </div>
    @endif

    @if ($kannSelbst)
        <label class="mt-3 flex items-start gap-2 {{ $wartenAufMich->isNotEmpty() ? 'zt-team-wartet-feld rounded-lg p-2' : '' }}">
            <input type="checkbox" name="zusammenfassen" value="1"
                   @checked(old('zusammenfassen', $getrennt ? $mein->zusammenfassen : true))
                   class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            <span class="text-sm text-gray-700">
                <span class="font-medium">Texte zusammenfassen</span>
                <span class="block text-xs text-gray-500">
                    @if ($getrennt)
                        Sobald alle Fachlehrer zustimmen, werden die Texte in einem gemeinsamen Feld zusammengeführt. Bis dahin stehen sie auf dem Zeugnis nacheinander.
                    @else
                        Häkchen entfernen trennt die Texte wieder – jeder Teiltext bekommt den aktuellen gemeinsamen Text als Ausgangspunkt.
                    @endif
                </span>
            </span>
        </label>
    @endif
</div>
<style>
    .zt-team-fremd { background: var(--color-gray-50, #f9fafb); }
    .zt-team-text { white-space: pre-wrap; color: var(--color-gray-400, #9ca3af); }
    .zt-team-wartet {
        background: var(--color-amber-50, #fffbeb);
        border: 2px solid var(--color-amber-400, #fbbf24);
        color: var(--color-amber-900, #78350f);
    }
    .zt-team-wartet i { color: var(--color-amber-500, #f59e0b); }
    .zt-team-wartet-feld { outline: 2px dashed var(--color-amber-400, #fbbf24); outline-offset: 2px; }
</style>
