<?php

namespace Intranet\Modules\Schulzeugnis\Support;

/**
 * Liefert ein garantiert schreibbares Temp-Verzeichnis für dompdf.
 *
 * Notwendig, weil sys_get_temp_dir() je nach Serverumgebung auf ein nicht
 * schreibbares Verzeichnis zeigen kann (beim PHP-Built-in-Server unter Windows z. B.
 * C:\WINDOWS). dompdf kann data-URI-Bilder dann nicht zwischenspeichern und verwirft
 * sie – Logos erscheinen als Broken-Image-Kreuz. Mit einem eigenen tempDir im
 * storage-Verzeichnis funktioniert die Bild-Einbettung überall.
 */
class PdfTemp
{
    public static function dir(): string
    {
        $dir = storage_path('app/dompdf-tmp');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }
}
