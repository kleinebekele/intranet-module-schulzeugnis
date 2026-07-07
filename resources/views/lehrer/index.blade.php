<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-module-icon name="user" class="text-2xl text-indigo-600" />
                <div>
                    <h1 class="text-xl font-semibold text-gray-800">Lehrer</h1>
                    <p class="text-sm text-gray-500">Schuljahr {{ $schuljahr->name }}</p>
                </div>
            </div>
            <a href="{{ route('module.schulzeugnis.lehrer.create', $schuljahr) }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                + Neuer Lehrer
            </a>
        </div>
    </x-slot>

    <div class="max-w-3xl space-y-3">
        <a href="{{ route('module.schulzeugnis.schuljahre.index') }}"
           class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
            &larr; Zurück zu den Schuljahren
        </a>

        {{-- Erfolgsmeldungen rendert das Core-Layout global – hier nur Fehler. --}}
        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
                {{ session('error') }}
            </div>
        @endif

        @forelse ($lehrer as $l)
            @php $konto = $l->core_user_id ? ($benutzer[$l->core_user_id] ?? null) : null; @endphp
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4">
                <div>
                    <h2 class="font-semibold text-gray-800">{{ $l->fullName() }}</h2>
                    <p class="mt-0.5 text-sm">
                        @if ($l->core_user_id && $konto)
                            <span class="text-green-600">Konto: {{ $konto->name }}</span>
                        @elseif ($l->core_user_id && ! $konto)
                            <span class="text-amber-600">Konto gelöscht (ID {{ $l->core_user_id }}) &ndash; Daten bleiben erhalten</span>
                        @else
                            <span class="text-gray-400">kein Konto verknüpft</span>
                        @endif
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('module.schulzeugnis.lehrer.edit', $l) }}"
                       class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-50">
                        Bearbeiten
                    </a>
                    <form method="POST" action="{{ route('module.schulzeugnis.lehrer.destroy', $l) }}"
                          onsubmit="return confirm('Lehrer {{ $l->fullName() }} wirklich löschen?');">
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
                Noch kein Lehrer in diesem Schuljahr. Lege den ersten an.
            </div>
        @endforelse
    </div>
</x-app-layout>
