<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Team-Teaching auch für den klassenweiten Text je Fach: gleiche Regeln wie bei den
 * Schülertexten (zeugnis_abschnitt_teiltexte) – eigener Teiltext je Fachlehrer, ein
 * gemeinsames Feld erst, wenn alle zustimmen (`zusammengefasst` am Klassentext).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('zeugnis_klassentext_teiltexte')) {
            Schema::create('zeugnis_klassentext_teiltexte', function (Blueprint $table) {
                $table->id();
                $table->foreignId('klassentext_id')->constrained('zeugnis_fach_klassentexte')->cascadeOnDelete();
                $table->unsignedBigInteger('lehrer_id')->index();
                $table->string('autor_name')->nullable();
                $table->text('inhalt')->nullable();
                $table->boolean('zusammenfassen')->default(false);
                $table->timestamps();
                $table->unique(['klassentext_id', 'lehrer_id']);
            });
        }

        if (! Schema::hasColumn('zeugnis_fach_klassentexte', 'zusammengefasst')) {
            Schema::table('zeugnis_fach_klassentexte', function (Blueprint $table) {
                $table->boolean('zusammengefasst')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('zeugnis_klassentext_teiltexte');
        if (Schema::hasColumn('zeugnis_fach_klassentexte', 'zusammengefasst')) {
            Schema::table('zeugnis_fach_klassentexte', function (Blueprint $table) {
                $table->dropColumn('zusammengefasst');
            });
        }
    }
};
