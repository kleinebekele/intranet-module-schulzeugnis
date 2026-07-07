<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zeugnis – das befüllte Zeugnis eines Schülers in einem Schuljahr.
 * Beim Abschließen (status = 'abgeschlossen') werden Name, Geburtsdatum und
 * Geburtsort als Klartext-Schnappschuss eingefroren – so bleibt ein Nachdruck
 * originalgetreu, auch wenn die Stammdaten sich später ändern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zeugnisse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schueler_id')->constrained('zeugnis_schuljahr_schueler')->cascadeOnDelete();
            $table->foreignId('format_id')->nullable()->constrained('zeugnis_formate')->nullOnDelete();
            $table->string('status')->default('entwurf'); // 'entwurf' | 'abgeschlossen'
            // Eingefrorene Schnappschüsse beim Abschließen:
            $table->timestamp('ausgestellt_am')->nullable();
            $table->string('ausgestellt_auf_name')->nullable();
            $table->date('ausgestellt_geburtsdatum')->nullable();
            $table->string('ausgestellt_geburtsort')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zeugnisse');
    }
};
