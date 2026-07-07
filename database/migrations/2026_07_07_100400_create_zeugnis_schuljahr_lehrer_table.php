<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lehrer je Schuljahr – modul-eigene Zeile pro Schuljahr.
 *
 * ENTKOPPLUNG: core_user_id ist NUR ein loser Wert, KEIN Fremdschlüssel auf die
 * Core-users-Tabelle. Der Core darf User jederzeit hart löschen – hier passiert
 * dann nichts. Der Klartext-Name bleibt als Schnappschuss erhalten.
 *
 * Zugriff: beim Login wird die core_user_id des eingeloggten Users gegen diese
 * Tabelle (aktives Schuljahr) abgeglichen. Treffer = darf seine Fächer sehen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zeugnis_schuljahr_lehrer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schuljahr_id')->constrained('zeugnis_schuljahre')->cascadeOnDelete();
            $table->unsignedBigInteger('core_user_id')->nullable()->index(); // loser Wert, KEIN FK
            $table->string('vorname')->nullable();        // Klartext-Schnappschuss
            $table->string('nachname')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zeugnis_schuljahr_lehrer');
    }
};
