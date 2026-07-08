<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beispiel-Zeugnistexte für die Layout-Vorschau (modulweit, nicht je Format).
 * Frei bearbeitbar im Designer; leert man alles, greifen wieder die
 * eingebauten Standardtexte. Reine Vorschau-Hilfe, kein echtes Zeugnis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zeugnis_beispieltexte', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('position')->default(0);
            $table->string('name');
            $table->longText('text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zeugnis_beispieltexte');
    }
};
