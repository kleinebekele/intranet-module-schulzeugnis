<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-module-icon name="book" class="text-2xl text-indigo-600" />
                <h1 class="text-xl font-semibold text-gray-800">Zeugnissprüche</h1>
            </div>
            <a href="{{ route('module.schulzeugnis.sprueche.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                + Neuer Spruch
            </a>
        </div>
    </x-slot>

    <div class="space-y-3">
        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ session('error') }}</div>
        @endif

        <p class="text-sm text-gray-500">
            Katalog der Zeugnissprüche. Der Klassenlehrer wählt daraus einen Spruch je Schüler aus und kann ihn danach frei bearbeiten.
        </p>

        @forelse ($sprueche as $spruch)
            <div class="flex items-start justify-between gap-4 rounded-xl border border-gray-200 bg-white p-4 {{ $spruch->aktiv ? '' : 'opacity-60' }}">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="w-6 text-center text-xs text-gray-400">{{ $spruch->reihenfolge }}</span>
                        <h2 class="font-semibold text-gray-800">{{ $spruch->titel ?: 'Ohne Titel' }}</h2>
                        @unless ($spruch->aktiv)
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500 ring-1 ring-gray-200">deaktiviert</span>
                        @endunless
                    </div>
                    <p class="mt-1 whitespace-pre-line pl-8 text-sm text-gray-600">{{ $spruch->vorschau(200) }}</p>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <a href="{{ route('module.schulzeugnis.sprueche.edit', $spruch) }}"
                       class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Bearbeiten</a>
                    <form method="POST" action="{{ route('module.schulzeugnis.sprueche.toggle', $spruch) }}">
                        @csrf
                        <button type="submit"
                                class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-50">
                            {{ $spruch->aktiv ? 'Deaktivieren' : 'Reaktivieren' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('module.schulzeugnis.sprueche.destroy', $spruch) }}"
                          onsubmit="return confirm('Diesen Spruch wirklich löschen?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="rounded-lg border border-red-200 px-2.5 py-1.5 text-sm text-red-600 hover:bg-red-50">Löschen</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                Noch kein Zeugnisspruch angelegt.
            </div>
        @endforelse
    </div>
</x-app-layout>
