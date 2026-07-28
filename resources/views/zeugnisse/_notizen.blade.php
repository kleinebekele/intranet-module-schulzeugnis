{{--
    Randnotizen (append-only): Liste + immer-leeres Eingabefeld.

    Parameter:
      $notizen       Collection (neueste zuerst)
      $action        POST-Ziel (Notiz anhängen)
      $kannNotieren  bool – darf der Benutzer Notizen anlegen?

    Das Eingabefeld nutzt bewusst KEIN old(): Es soll bei jedem Aufruf leer sein,
    jede abgeschickte Eingabe wird als NEUE Notiz angehängt (nie überschrieben).
--}}
<div class="rounded-xl border border-gray-200 bg-white p-5">
    <h2 class="text-sm font-semibold text-gray-700">Notizen <span class="font-normal text-gray-400">(intern, erscheinen nicht auf dem Zeugnis)</span></h2>

    @if ($notizen->isEmpty())
        <p class="mt-2 text-sm text-gray-400">Noch keine Notizen.</p>
    @else
        <ul class="zt-log">
            @foreach ($notizen as $n)
                <li class="zt-log-item">
                    <div class="text-xs text-gray-500">
                        {{ $n->created_at?->format('d.m.Y H:i') }} Uhr &middot; {{ $n->autor_name }}
                    </div>
                    <div class="mt-0.5 text-sm text-gray-700" style="white-space: pre-line;">{{ $n->text }}</div>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($kannNotieren)
        <form method="POST" action="{{ $action }}" class="mt-3">
            @csrf
            <textarea name="notiz_text" rows="2" required maxlength="2000" placeholder="Neue Notiz …"
                      class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            @error('notiz_text')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <button type="submit"
                    class="mt-2 inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                <i class="bx bx-note"></i> Notiz hinzufügen
            </button>
        </form>
    @endif
</div>
