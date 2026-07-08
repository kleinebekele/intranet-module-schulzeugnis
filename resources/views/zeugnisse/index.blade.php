<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="book" class="text-2xl text-indigo-600" />
            <div>
                <h1 class="text-xl font-semibold text-gray-800">Zeugnisse</h1>
                <p class="text-sm text-gray-500">Klasse {{ $klasse->name }} &middot; Schuljahr {{ $klasse->schuljahr->name }}</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-3xl space-y-4">
        <a href="{{ route('module.schulzeugnis.klassen.index', $klasse->schuljahr_id) }}"
           class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
            &larr; Zurück zu den Klassen
        </a>

        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
                {{ session('error') }}
            </div>
        @endif

        @forelse ($schueler as $s)
            @php
                $z = $s->zeugnis;
                $gesamt = $z ? $z->abschnitte->count() : 0;
                $fertig = $z ? $z->abschnitte->where('status', 'fertig')->count() : 0;
            @endphp
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">{{ $s->nachname }}, {{ $s->vorname }}</h2>
                    @if ($z)
                        <div class="mt-1 flex items-center gap-2 text-sm">
                            @if ($z->istAbgeschlossen())
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">abgeschlossen</span>
                            @else
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Entwurf</span>
                            @endif
                            <span class="text-gray-500">{{ $fertig }} / {{ $gesamt }} Abschnitte fertig</span>
                        </div>
                    @else
                        <p class="mt-1 text-sm italic text-gray-400">noch kein Zeugnis</p>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    @if ($z)
                        <a href="{{ route('module.schulzeugnis.zeugnisse.edit', $z) }}"
                           class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                            {{ $z->istAbgeschlossen() ? 'Ansehen' : 'Bearbeiten' }}
                        </a>
                    @else
                        <form method="POST" action="{{ route('module.schulzeugnis.zeugnisse.store', [$klasse, $s]) }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-lg border border-indigo-200 px-3 py-1.5 text-sm font-medium text-indigo-600 hover:bg-indigo-50">
                                Zeugnis anlegen
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                Noch keine Schüler in dieser Klasse.
                <a href="{{ route('module.schulzeugnis.schueler.index', $klasse->schuljahr_id) }}" class="text-indigo-600 hover:text-indigo-700">Schüler anlegen</a>.
            </div>
        @endforelse
    </div>
</x-app-layout>
