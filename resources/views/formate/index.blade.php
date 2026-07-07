<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-module-icon name="category" class="text-2xl text-indigo-600" />
                <h1 class="text-xl font-semibold text-gray-800">Zeugnisformate</h1>
            </div>
            <a href="{{ route('module.schulzeugnis.formate.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                + Neues Format
            </a>
        </div>
    </x-slot>

    <div class="max-w-3xl space-y-3">
        {{-- Erfolgsmeldungen rendert das Core-Layout global – hier nur Fehler. --}}
        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
                {{ session('error') }}
            </div>
        @endif

        @forelse ($formate as $format)
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 {{ $format->aktiv ? '' : 'opacity-60' }}">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="font-semibold text-gray-800">{{ $format->name }}</h2>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $format->typ === 'noten' ? 'bg-amber-100 text-amber-700' : 'bg-sky-100 text-sky-700' }}">
                            {{ $format->typLabel() }}
                        </span>
                        @unless ($format->aktiv)
                            <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600">archiviert</span>
                        @endunless
                    </div>
                    @if ($format->beschreibung)
                        <p class="mt-1 text-sm text-gray-500">{{ $format->beschreibung }}</p>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('module.schulzeugnis.formate.designer', $format) }}"
                       class="rounded-lg bg-indigo-600 px-2.5 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                        Designer
                    </a>
                    <a href="{{ route('module.schulzeugnis.formate.vorschau', $format) }}" target="_blank"
                       class="rounded-lg border border-indigo-200 px-2.5 py-1.5 text-sm text-indigo-600 hover:bg-indigo-50">
                        Vorschau
                    </a>
                    <a href="{{ route('module.schulzeugnis.formate.pdf', $format) }}" target="_blank"
                       class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-50">
                        PDF
                    </a>
                    <a href="{{ route('module.schulzeugnis.formate.edit', $format) }}"
                       class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-50">
                        Bearbeiten
                    </a>
                    <form method="POST" action="{{ route('module.schulzeugnis.formate.duplicate', $format) }}">
                        @csrf
                        <button type="submit"
                                class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-50">
                            Duplizieren
                        </button>
                    </form>
                    <form method="POST" action="{{ route('module.schulzeugnis.formate.toggle', $format) }}">
                        @csrf
                        <button type="submit"
                                class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-50">
                            {{ $format->aktiv ? 'Archivieren' : 'Reaktivieren' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('module.schulzeugnis.formate.destroy', $format) }}"
                          onsubmit="return confirm('Format {{ $format->name }} wirklich löschen?');">
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
                Noch kein Format angelegt. Lege z. B. „Textzeugnis Unterstufe" oder „Notenzeugnis Abschluss" an.
            </div>
        @endforelse
    </div>
</x-app-layout>
