<?php

namespace Intranet\Modules\Schulzeugnis\Support;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

/**
 * Prüft, ob Ghostscript auf dem Server verfügbar ist. Ghostscript wird für die
 * PDF/A-2b-Langzeitarchivierung der Zeugnisse benötigt (dompdf erzeugt nur normale PDFs).
 */
class Ghostscript
{
    /** Ist das Ghostscript-Binary (gs / gswin) aufrufbar? Ergebnis wird kurz gecacht. */
    public static function verfuegbar(): bool
    {
        return (bool) Cache::remember('schulzeugnis:gs-verfuegbar', now()->addMinutes(10), function () {
            foreach (['gs', 'gswin64c', 'gswin32c'] as $bin) {
                try {
                    $p = new Process([$bin, '--version']);
                    $p->setTimeout(5);
                    $p->run();
                    if ($p->isSuccessful()) {
                        return true;
                    }
                } catch (\Throwable $e) {
                    // nächster Kandidat
                }
            }

            return false;
        });
    }
}
