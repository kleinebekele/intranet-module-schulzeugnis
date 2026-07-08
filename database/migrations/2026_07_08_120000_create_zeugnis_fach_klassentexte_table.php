<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Klassenweiter Text je Fach: ein gemeinsamer Text pro (Klasse, Fach), der auf
 * dem Zeugnis VOR dem individuellen Schülertext erscheint. Genau eine Zeile je
 * Klasse+Fach.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zeugnis_fach_klassentexte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('klasse_id')->constrained('zeugnis_klassen')->cascadeOnDelete();
            $table->foreignId('fach_id')->constrained('zeugnis_faecher')->cascadeOnDelete();
            $table->longText('text')->nullable();
            $table->timestamps();
            $table->unique(['klasse_id', 'fach_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zeugnis_fach_klassentexte');
    }
};
