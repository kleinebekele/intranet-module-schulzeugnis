<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="book" class="text-2xl text-indigo-600" />
            <div>
                <h1 class="text-xl font-semibold text-gray-800">Klassentext bearbeiten</h1>
                <p class="text-sm text-gray-500">
                    Klasse {{ $klasse->name }} &middot; Schuljahr {{ $klasse->schuljahr->name }} &middot;
                    {{ $fach?->name ?? 'Haupttext (Klassenlehrer)' }}
                </p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-2xl space-y-4">
        <a href="{{ route('module.schulzeugnis.klassenraeume.zeugnisse.index', $klasse) }}"
           class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
            &larr; Zurück zur Zeugnisliste
        </a>

        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ session('error') }}</div>
        @endif

        <div class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 text-sm text-indigo-900">
            Dieser Text gilt <strong>klassenweit</strong> für {{ $fach?->name ?? 'den Haupttext' }} und erscheint auf jedem
            Zeugnis dieser Klasse <strong>vor</strong> dem jeweiligen Schülertext.
        </div>

        <form method="POST" action="{{ route('module.schulzeugnis.klassenraeume.klassentexte.update', ['klasse' => $klasse, 'fach' => $fachParam]) }}"
              class="space-y-4 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @method('PUT')

            <div>
                <label for="text" class="block text-sm font-medium text-gray-700">Klassenweiter Text</label>
                <textarea name="text" id="text" rows="10"
                          class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="Text, der für alle Schüler dieser Klasse gilt …">{{ old('text', $klassentext->text) }}</textarea>
                @error('text')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-2">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Speichern
                </button>
                <a href="{{ route('module.schulzeugnis.klassenraeume.zeugnisse.index', $klasse) }}"
                   class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">Abbrechen</a>
            </div>
        </form>
    </div>
</x-app-layout>
