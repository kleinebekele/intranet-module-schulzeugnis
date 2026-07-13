<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Klassenweiter Text bekommt eine interne Notiz – wie ein Abschnitt. Erscheint
 * nicht auf dem Zeugnis, nur im Editor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnis_fach_klassentexte', function (Blueprint $table) {
            $table->longText('notiz')->nullable()->after('text');
        });
    }

    public function down(): void
    {
        Schema::table('zeugnis_fach_klassentexte', function (Blueprint $table) {
            $table->dropColumn('notiz');
        });
    }
};
