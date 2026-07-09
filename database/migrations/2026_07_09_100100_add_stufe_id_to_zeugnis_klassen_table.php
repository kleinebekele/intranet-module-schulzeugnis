<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ordnet jede Klasse einer Schulstufe zu (nullable – eine Klasse ohne Stufe
 * bekommt in der Klassenräume-Ansicht eine neutrale Türfarbe).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnis_klassen', function (Blueprint $table) {
            $table->foreignId('stufe_id')
                ->nullable()
                ->after('name')
                ->constrained('zeugnis_stufen')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('zeugnis_klassen', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stufe_id');
        });
    }
};
