<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-module-icon name="calendar" class="text-2xl text-indigo-600" />
                <h1 class="text-xl font-semibold text-gray-800">Schuljahre</h1>
            </div>
            <a href="{{ route('module.schulzeugnis.schuljahre.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                + Neues Schuljahr
            </a>
        </div>
    </x-slot>

    <div class="max-w-3xl space-y-4">
        {{-- Erfolgsmeldungen (session('status')) rendert das Core-Layout global – hier NICHT wiederholen. --}}
        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
                {{ session('error') }}
            </div>
        @endif

        @forelse ($schuljahre as $schuljahr)
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg font-semibold text-gray-800">{{ $schuljahr->name }}</h2>
                        @if ($schuljahr->is_active)
                            <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">aktiv</span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-gray-500">
                        @if ($schuljahr->start_date && $schuljahr->end_date)
                            {{ $schuljahr->start_date->format('d.m.Y') }} &ndash; {{ $schuljahr->end_date->format('d.m.Y') }}
                        @else
                            <span class="italic text-gray-400">kein Zeitraum hinterlegt</span>
                        @endif
                    </p>
                    @if ($schuljahr->eingabe_frist || $schuljahr->ausgabe_datum)
                        <p class="mt-0.5 text-sm text-gray-500">
                            @if ($schuljahr->eingabe_frist)
                                Dateneingabe bis {{ $schuljahr->eingabe_frist->format('d.m.Y') }}
                            @endif
                            @if ($schuljahr->ausgabe_datum)
                                @if ($schuljahr->eingabe_frist) &middot; @endif Zeugnisausgabe {{ $schuljahr->ausgabe_datum->format('d.m.Y') }}
                            @endif
                        </p>
                    @endif

                    <div class="mt-2 flex items-center gap-4 text-sm font-medium">
                        <a href="{{ route('module.schulzeugnis.klassen.index', $schuljahr) }}"
                           class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700">
                            {{ $schuljahr->klassen_count }} {{ $schuljahr->klassen_count === 1 ? 'Klasse' : 'Klassen' }} &rarr;
                        </a>
                        <a href="{{ route('module.schulzeugnis.lehrer.index', $schuljahr) }}"
                           class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700">
                            {{ $schuljahr->lehrer_count }} Lehrer &rarr;
                        </a>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    @unless ($schuljahr->is_active)
                        <form method="POST" action="{{ route('module.schulzeugnis.schuljahre.activate', $schuljahr) }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-50">
                                aktiv setzen
                            </button>
                        </form>
                    @endunless
                    <a href="{{ route('module.schulzeugnis.schuljahre.edit', $schuljahr) }}"
                       class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-50">
                        Bearbeiten
                    </a>
                    <form method="POST" action="{{ route('module.schulzeugnis.schuljahre.destroy', $schuljahr) }}"
                          onsubmit="return confirm('Schuljahr {{ $schuljahr->name }} wirklich löschen?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="rounded-lg border border-red-200 px-2.5 py-1.5 text-sm text-red-600 hover:bg-red-50">
                            Löschen
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                Noch kein Schuljahr angelegt. Lege das erste an – alles Weitere hängt daran.
            </div>
        @endforelse
    </div>
</x-app-layout>
