@php
    $titel = $abschnitt->typ === 'haupttext' ? 'Haupttext' : ($abschnitt->fach?->name ?? 'Fachtext');
    $istNote = $abschnitt->typ === 'note';
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-module-icon name="book" class="text-2xl text-indigo-600" />
                <div>
                    <h1 class="text-xl font-semibold text-gray-800">{{ $titel }}</h1>
                    <p class="text-sm text-gray-500">
                        {{ $schueler?->fullName() }} &middot; Klasse {{ $schueler?->klasse?->name ?? '—' }} &middot; Schuljahr {{ $schueler?->klasse?->schuljahr?->name ?? '—' }}
                    </p>
                </div>
            </div>
            @if ($readonly)
                <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-700">Zeugnis abgeschlossen</span>
            @endif
        </div>
    </x-slot>

    <div class="max-w-2xl space-y-4">
        <div class="flex items-center justify-between">
            <a href="{{ route('module.schulzeugnis.zeugnisse.index', $schueler?->klasse) }}"
               class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                &larr; Zurück zur Zeugnis-Tabelle
            </a>
            <a href="{{ route('module.schulzeugnis.zeugnisse.edit', $zeugnis) }}"
               class="text-sm text-gray-500 hover:text-gray-700">Ganzes Zeugnis &rarr;</a>
        </div>

        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ session('error') }}</div>
        @endif

        @if ($readonly)
            <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                Das Zeugnis ist abgeschlossen – der Text ist schreibgeschützt.
            </div>
        @endif

        {{-- Bearbeitung --}}
        <form method="POST" action="{{ route('module.schulzeugnis.abschnitte.update', $abschnitt) }}"
              class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            @csrf
            @method('PUT')

            @if ($abschnitt->autor_name)
                <p class="text-xs text-gray-400">Autor: {{ $abschnitt->autor_name }}</p>
            @endif

            @if ($klassentext)
                <div class="rounded-lg bg-indigo-50/60 p-3">
                    <label class="block text-sm font-medium text-gray-700">Klassenweiter Text
                        <textarea name="klassentext" rows="3" @disabled($readonly)
                                  class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Gemeinsamer Text für alle Schüler …">{{ old('klassentext', $klassentext->text) }}</textarea>
                    </label>
                    <p class="mt-1 text-xs text-gray-500">Gilt für <strong>alle Schüler</strong> der Klasse in diesem Fach und steht auf dem Zeugnis <strong>vor</strong> dem Schülertext.</p>
                    <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="klassentext_neue_zeile" value="1" @checked($abschnitt->klassentext_neue_zeile) @disabled($readonly)
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Schülertext in neuer Zeile beginnen (statt direkt anschließen)
                    </label>
                </div>
            @endif

            @if ($istNote)
                <label class="block text-sm font-medium text-gray-700">Note
                    <input type="text" name="note" value="{{ old('note', $abschnitt->note) }}" @disabled($readonly)
                           class="mt-1 block w-32 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <label class="block text-sm font-medium text-gray-700">Ergänzung (optional)
                    <input type="text" name="inhalt" value="{{ old('inhalt', $abschnitt->inhalt) }}" @disabled($readonly)
                           class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
            @else
                <label class="block text-sm font-medium text-gray-700">{{ $klassentext ? 'Schülertext' : 'Text' }}
                    <textarea name="inhalt" rows="8" @disabled($readonly)
                              class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Text …">{{ old('inhalt', $abschnitt->inhalt) }}</textarea>
                </label>
            @endif

            <label class="block text-sm font-medium text-gray-700">Notiz <span class="text-gray-400">(intern, erscheint nicht auf dem Zeugnis)</span>
                <textarea name="notiz" rows="2" @disabled($readonly)
                          class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="z. B. Rückfrage, Erinnerung …">{{ old('notiz', $abschnitt->notiz) }}</textarea>
            </label>

            <label class="block text-sm font-medium text-gray-700">Bearbeitungsstatus
                <select name="status" @disabled($readonly)
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($stati as $key => $meta)
                        <option value="{{ $key }}" @selected(old('status', $abschnitt->status) === $key)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </label>

            @unless ($readonly)
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Speichern
                </button>
            @endunless
        </form>

        {{-- Änderungsverlauf / Wiederherstellung --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-gray-700">Änderungsverlauf</h2>
            @if ($verlauf->isEmpty())
                <p class="mt-2 text-sm text-gray-400">Noch keine Textänderungen protokolliert.</p>
            @else
                <ul class="mt-3 space-y-3">
                    @foreach ($verlauf as $eintrag)
                        <li class="border-l-2 border-gray-200 pl-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-xs text-gray-500">
                                    {{ $eintrag->created_at?->format('d.m.Y H:i') }} Uhr
                                    @if ($eintrag->akteur_name) &middot; {{ $eintrag->akteur_name }} @endif
                                    @if ($eintrag->aktion === 'abschnitt_wiederhergestellt')
                                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700">wiederhergestellt</span>
                                    @endif
                                </div>
                                @unless ($readonly)
                                    <form method="POST" action="{{ route('module.schulzeugnis.abschnitte.wiederherstellen', $abschnitt) }}"
                                          onsubmit="return confirm('Diesen Stand wiederherstellen? Der aktuelle Text wird ersetzt (bleibt im Verlauf).');">
                                        @csrf
                                        <input type="hidden" name="protokoll_id" value="{{ $eintrag->id }}">
                                        <button type="submit" class="text-xs text-indigo-600 hover:underline">Wiederherstellen</button>
                                    </form>
                                @endunless
                            </div>
                            <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ \Illuminate\Support\Str::limit($eintrag->neu_wert, 240) ?: '(leer)' }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-app-layout>
