<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schuljahr – der Anker des Moduls. Klassen, Schüler, Lehrer, Lehraufträge und
 * Zeugnisse hängen alle an einem Schuljahr. Der Jahres-Import ist additiv:
 * neue Schuljahre kommen dazu, alte bleiben unverändert bestehen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zeugnis_schuljahre', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // z. B. "2026/2027"
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(false);  // das aktuell aktive Schuljahr
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zeugnis_schuljahre');
    }
};
