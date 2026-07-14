<?php

namespace Intranet\Modules\Schulzeugnis\Support\Import;

/**
 * Fachliche Fehlermeldung während eines Imports (z. B. fehlende Pflichtspalte,
 * unlesbare Datei). Wird im Controller gefangen und dem Nutzer als Hinweis gezeigt.
 */
class ImportFehler extends \RuntimeException
{
}
