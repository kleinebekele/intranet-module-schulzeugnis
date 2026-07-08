<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="home" class="text-2xl text-indigo-600" />
            <div>
                <h1 class="text-xl font-semibold text-gray-800">Klassenräume</h1>
                <p class="text-sm text-gray-500">Schuljahr {{ $schuljahr->name }} &middot; wähle einen Klassenraum</p>
            </div>
        </div>
    </x-slot>

    <style>
        .kr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 40px 20px;
            padding: 8px 4px 24px;
        }
        .kr-tuer { text-decoration: none; display: flex; flex-direction: column; align-items: center; }

        /* Rahmen + Türöffnung (der Rahmen trägt die 3D-Perspektive für das Türblatt) */
        .kr-rahmen {
            position: relative;
            width: 132px;
            height: 208px;
            margin: 0 auto;
            perspective: 900px;
            border-radius: 8px 8px 3px 3px;
            /* heller Türrahmen ringsum */
            background: linear-gradient(180deg, #efe9df, #e3dccf);
            padding: 9px 9px 0;
            box-shadow: 0 10px 18px -10px rgba(0,0,0,.45);
        }
        /* weicher Bodenschatten */
        .kr-rahmen::after {
            content: "";
            position: absolute;
            left: 50%; bottom: -12px;
            width: 120px; height: 16px;
            transform: translateX(-50%);
            background: radial-gradient(ellipse at center, rgba(0,0,0,.28), rgba(0,0,0,0) 70%);
        }
        /* dunkle Öffnung dahinter (wird beim Aufschwingen sichtbar) */
        .kr-oeffnung {
            position: absolute;
            inset: 9px 9px 0;
            background: linear-gradient(180deg, #2b2f36, #1b1e23);
            border-radius: 5px 5px 0 0;
            box-shadow: inset 0 0 18px rgba(0,0,0,.6);
        }

        /* das schwingende Türblatt */
        .kr-blatt {
            position: absolute;
            inset: 9px 9px 0;
            border-radius: 5px 5px 0 0;
            transform-origin: left center;
            transform: rotateY(0deg);
            transition: transform .55s cubic-bezier(.34,.8,.32,1), box-shadow .55s;
            transform-style: preserve-3d;
            box-shadow: inset 0 0 0 2px rgba(0,0,0,.08);
            cursor: pointer;
        }
        .kr-blau  { background: linear-gradient(135deg, #5b87ac 0%, #3f6588 55%, #375a79 100%); }
        .kr-gruen { background: linear-gradient(135deg, #5aa15a 0%, #418141 55%, #3a743a 100%); }

        .kr-tuer:hover .kr-blatt,
        .kr-tuer:focus-visible .kr-blatt {
            transform: rotateY(-78deg);
            box-shadow: 6px 0 22px -4px rgba(0,0,0,.5), inset 0 0 0 2px rgba(0,0,0,.08);
        }

        /* Füllungen (2×2 vertiefte Panels) */
        .kr-panels {
            position: absolute;
            inset: 12px 12px 14px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1.15fr 1fr;
            gap: 10px;
        }
        .kr-panel {
            border-radius: 4px;
            background: linear-gradient(135deg, rgba(255,255,255,.10), rgba(0,0,0,.06));
            box-shadow: inset 2px 2px 4px rgba(0,0,0,.28), inset -2px -2px 4px rgba(255,255,255,.16);
        }
        /* Türgriff */
        .kr-griff {
            position: absolute;
            right: 12px; top: 50%;
            width: 7px; height: 24px;
            transform: translateY(-50%);
            border-radius: 4px;
            background: linear-gradient(180deg, #f4d35e, #d9a521);
            box-shadow: 0 1px 2px rgba(0,0,0,.4);
        }

        .kr-label { margin-top: 14px; font-weight: 600; color: #374151; text-align: center; }
        .kr-sub { font-size: 12px; color: #9ca3af; text-align: center; margin-top: 1px; }
        .kr-tuer:hover .kr-label { color: #4f46e5; }
    </style>

    <div class="space-y-3">
        @if (session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ session('error') }}</div>
        @endif

        @if ($klassen->isEmpty())
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-10 text-center text-gray-500">
                In diesem Schuljahr gibt es noch keine Klassen.
                <a href="{{ route('module.schulzeugnis.klassen.index', $schuljahr) }}" class="text-indigo-600 hover:text-indigo-700">Klassen anlegen</a>.
            </div>
        @else
            <div class="kr-grid">
                @foreach ($klassen as $klasse)
                    @php $gruen = is_numeric($klasse->name) && (int) $klasse->name >= 9; @endphp
                    <a class="kr-tuer" href="{{ route('module.schulzeugnis.zeugnisse.index', $klasse) }}"
                       title="Zeugnisliste der Klasse {{ $klasse->name }}">
                        <div class="kr-rahmen">
                            <div class="kr-oeffnung"></div>
                            <div class="kr-blatt {{ $gruen ? 'kr-gruen' : 'kr-blau' }}">
                                <div class="kr-panels">
                                    <div class="kr-panel"></div>
                                    <div class="kr-panel"></div>
                                    <div class="kr-panel"></div>
                                    <div class="kr-panel"></div>
                                </div>
                                <div class="kr-griff"></div>
                            </div>
                        </div>
                        <div class="kr-label">Klasse {{ $klasse->name }}</div>
                        @if ($klasse->klassenlehrer)
                            <div class="kr-sub">{{ $klasse->klassenlehrer->fullName() }}</div>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
