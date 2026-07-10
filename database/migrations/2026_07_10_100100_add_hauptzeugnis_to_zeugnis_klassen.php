<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Je Klasse konfigurierbar, welche Zeugnisse ihre Schüler bekommen:
 *  - hat_fachzeugnis:  das bisherige Fächer-Zeugnis (Standard AN)
 *  - hat_hauptzeugnis: eigenständiges Hauptzeugnis aus Fachbereichen (Standard AUS)
 *  - hauptzeugnis_format_id: Vorlage des Hauptzeugnisses (das Fachzeugnis nutzt
 *    weiterhin standard_format_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnis_klassen', function (Blueprint $table) {
            $table->boolean('hat_fachzeugnis')->default(true)->after('standard_format_id');
            $table->boolean('hat_hauptzeugnis')->default(false)->after('hat_fachzeugnis');
            $table->foreignId('hauptzeugnis_format_id')
                ->nullable()
                ->after('hat_hauptzeugnis')
                ->constrained('zeugnis_formate')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('zeugnis_klassen', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hauptzeugnis_format_id');
            $table->dropColumn(['hat_fachzeugnis', 'hat_hauptzeugnis']);
        });
    }
};
