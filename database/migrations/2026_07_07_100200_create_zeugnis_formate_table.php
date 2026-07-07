<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zeugnisformat – pflegbare Vorlage. Legt Typ (Freitext vs. Noten) fest.
 * Zuweisung mit Vererbung: Standard je Klasse, Override je Schüler.
 * Start mit festen Typen; ein freier Abschnitts-Baukasten kann später andocken.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zeugnis_formate', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // z. B. "Textzeugnis Unterstufe"
            $table->string('typ')->default('text');       // 'text' | 'noten'
            $table->text('beschreibung')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zeugnis_formate');
    }
};
