<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Team-Teaching: Haben zwei oder mehr Lehrkräfte einen Lehrauftrag im selben Fach einer
 * Klasse, schreibt zunächst jede ihren eigenen Teiltext. Stimmen alle dem Zusammenfassen
 * zu, wird daraus ein gemeinsames Feld (`zusammengefasst` am Abschnitt); nimmt eine die
 * Zustimmung zurück, bekommt jeder Teiltext den aktuellen gemeinsamen Text.
 *
 * Auf dem Zeugnis steht immer `zeugnis_abschnitte.inhalt` – in der getrennten Phase die
 * Teiltexte nacheinander (gleiche nur einmal). lehrer_id ist ein loser Verweis, der Name
 * als Schnappschuss (Insel-Prinzip).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('zeugnis_abschnitt_teiltexte')) {
            Schema::create('zeugnis_abschnitt_teiltexte', function (Blueprint $table) {
                $table->id();
                $table->foreignId('abschnitt_id')->constrained('zeugnis_abschnitte')->cascadeOnDelete();
                $table->unsignedBigInteger('lehrer_id')->index();
                $table->string('autor_name')->nullable();
                $table->text('inhalt')->nullable();
                $table->boolean('zusammenfassen')->default(false);
                $table->timestamps();
                $table->unique(['abschnitt_id', 'lehrer_id']);
            });
        }

        if (! Schema::hasColumn('zeugnis_abschnitte', 'zusammengefasst')) {
            Schema::table('zeugnis_abschnitte', function (Blueprint $table) {
                $table->boolean('zusammengefasst')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('zeugnis_abschnitt_teiltexte');
        if (Schema::hasColumn('zeugnis_abschnitte', 'zusammengefasst')) {
            Schema::table('zeugnis_abschnitte', function (Blueprint $table) {
                $table->dropColumn('zusammengefasst');
            });
        }
    }
};
