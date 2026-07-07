<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protokoll – append-only. Jede bedeutsame Handlung wird als eigene Zeile
 * geschrieben und NIE geändert oder gelöscht (nur created_at, kein updated_at).
 *
 * Alle Bezüge (schuljahr_id, zeugnis_id, abschnitt_id) sind LOSE Werte ohne FK,
 * damit Protokollzeilen niemals per Cascade verschwinden. Der Akteur wird als
 * eingefrorener Schnappschuss gespeichert (Name/Rolle/ID zum Zeitpunkt der Tat)
 * und überlebt die Löschung des Core-Users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zeugnis_protokoll', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schuljahr_id')->nullable()->index();
            $table->unsignedBigInteger('zeugnis_id')->nullable()->index();
            $table->unsignedBigInteger('abschnitt_id')->nullable();
            $table->string('aktion');                     // 'angelegt','geaendert','abgeschlossen','geoeffnet','importiert', ...
            $table->string('beschreibung')->nullable();
            $table->text('alt_wert')->nullable();
            $table->text('neu_wert')->nullable();
            // Akteur als eingefrorener Schnappschuss:
            $table->unsignedBigInteger('akteur_user_id')->nullable();
            $table->string('akteur_name')->nullable();
            $table->string('akteur_rolle')->nullable();
            $table->timestamp('created_at')->nullable();  // append-only: nur created_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zeugnis_protokoll');
    }
};
