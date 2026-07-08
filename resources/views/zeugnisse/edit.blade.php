@php $readonly = $zeugnis->istAbgeschlossen(); @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-module-icon name="book" class="text-2xl text-indigo-600" />
                <div>
                    <h1 class="text-xl font-semibold text-gray-800">Zeugnis · {{ $schueler->fullName() }}</h1>
                    <p class="text-sm text-gray-500">
                        Klasse {{ $schueler->klasse?->name ?? '—' }} &middot; Schuljahr {{ $schueler->klasse?->schuljahr?->name ?? '—' }}
                        @if ($zeugnis->format) &middot; {{ $zeugnis->format->name }} ({{ $zeugnis->format->typLabel() }}) @endif
                    </p>
                </div>
            </div>
            @if ($readonly)
                <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-700">abgeschlossen</span>
            @else
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700">Entwurf</span>
            @endif
        </div>
    </x-slot>

    <div class="max-w-2xl space-y-4">
        <a href="{{ route('module.schulzeugnis.zeugnisse.index', $schueler->klasse) }}"
           class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
            &larr; Zurück zur Zeugnisliste
        </a>

        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
                {{ session('error') }}
            </div>
        @endif

        @if ($readonly)
            <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                <p class="font-semibold">Eingefroren am {{ $zeugnis->ausgestellt_am?->format('d.m.Y H:i') }} Uhr.</p>
                @php
                    $gebText = collect([
                        $zeugnis->ausgestellt_geburtsdatum ? 'geboren am ' . $zeugnis->ausgestellt_geburtsdatum->format('d.m.Y') : null,
                        $zeugnis->ausgestellt_geburtsort ? 'in ' . $zeugnis->ausgestellt_geburtsort : null,
                    ])->filter()->implode(' ');
                @endphp
                <p class="mt-1">
                    Ausgestellt auf <strong>{{ $zeugnis->ausgestellt_auf_name }}</strong>{{ $gebText ? ', ' . $gebText : '' }}.
                </p>
                <p class="mt-1 text-green-700">Der Inhalt ist schreibgeschützt.</p>
            </div>
        @endif

        <form method="POST" action="{{ route('module.schulzeugnis.zeugnisse.update', $zeugnis) }}"
              class="space-y-4">
            @csrf
            @method('PUT')

            @foreach ($abschnitte as $a)
                <div class="rounded-xl border border-gray-200 bg-white p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold text-gray-800">
                            @if ($a->typ === 'haupttext')
                                Haupttext
                            @else
                                {{ $a->fach?->name ?? 'Fach' }}
                            @endif
                        </h2>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" name="abschnitte[{{ $a->id }}][status]" value="fertig"
                                   @checked($a->status === 'fertig') @disabled($readonly)
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            fertig
                        </label>
                    </div>

                    @if ($a->autor_name)
                        <p class="mt-0.5 text-xs text-gray-400">Autor: {{ $a->autor_name }}</p>
                    @endif

                    @if ($a->typ === 'note')
                        <div class="mt-3 flex items-center gap-3">
                            <input type="text" name="abschnitte[{{ $a->id }}][note]" value="{{ $a->note }}"
                                   placeholder="Note" @disabled($readonly)
                                   class="w-24 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <input type="text" name="abschnitte[{{ $a->id }}][inhalt]" value="{{ $a->inhalt }}"
                                   placeholder="Ergänzung (optional)" @disabled($readonly)
                                   class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    @else
                        <textarea name="abschnitte[{{ $a->id }}][inhalt]" rows="5" @disabled($readonly)
                                  class="mt-3 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Text …">{{ $a->inhalt }}</textarea>
                    @endif
                </div>
            @endforeach

            @unless ($readonly)
                <div class="flex items-center gap-3">
                    <button type="submit"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Speichern
                    </button>
                </div>
            @endunless
        </form>

        {{-- Abschließen / Wieder öffnen --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5">
            @if (! $readonly)
                <h2 class="text-sm font-semibold text-gray-700">Zeugnis abschließen</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Beim Abschließen werden Name, Geburtsdatum und -ort sowie alle Texte eingefroren. Danach ist das Zeugnis schreibgeschützt.
                </p>
                <form method="POST" action="{{ route('module.schulzeugnis.zeugnisse.abschliessen', $zeugnis) }}"
                      onsubmit="return confirm('Zeugnis für {{ $schueler->fullName() }} abschließen und einfrieren?');"
                      class="mt-3">
                    @csrf
                    <button type="submit"
                            class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                        Abschließen &amp; einfrieren
                    </button>
                </form>
            @else
                <h2 class="text-sm font-semibold text-gray-700">Abschluss zurücknehmen</h2>
                @if ($istAdmin)
                    <p class="mt-1 text-sm text-gray-500">Als Administrator kannst du das Zeugnis wieder öffnen, um es erneut zu bearbeiten.</p>
                    <form method="POST" action="{{ route('module.schulzeugnis.zeugnisse.wiederoeffnen', $zeugnis) }}"
                          onsubmit="return confirm('Abschluss zurücknehmen und Zeugnis wieder öffnen?');"
                          class="mt-3">
                        @csrf
                        <button type="submit"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Wieder öffnen
                        </button>
                    </form>
                @else
                    <p class="mt-1 text-sm text-gray-500">Nur Administratoren können ein abgeschlossenes Zeugnis wieder öffnen.</p>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
