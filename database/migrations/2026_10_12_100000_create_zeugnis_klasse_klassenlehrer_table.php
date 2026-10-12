<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Klassenleitung: beliebig viele Klassenlehrer je Klasse, in Reihenfolge.
 *
 * `zeugnis_klassen.klassenlehrer_id` bleibt als Verweis auf den ERSTEN Klassenlehrer
 * erhalten (Autor neu angelegter Haupttexte/Sprüche, Import). Bestehende Einträge
 * werden als erste Zeile übernommen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('zeugnis_klasse_klassenlehrer')) {
            Schema::create('zeugnis_klasse_klassenlehrer', function (Blueprint $table) {
                $table->id();
                $table->foreignId('klasse_id')->constrained('zeugnis_klassen')->cascadeOnDelete();
                $table->foreignId('lehrer_id')->constrained('zeugnis_schuljahr_lehrer')->cascadeOnDelete();
                $table->unsignedInteger('reihenfolge')->default(0);
                $table->timestamps();
                $table->unique(['klasse_id', 'lehrer_id']);
            });
        }

        $jetzt = now();
        DB::table('zeugnis_klassen')
            ->whereNotNull('klassenlehrer_id')
            ->whereIn('klassenlehrer_id', DB::table('zeugnis_schuljahr_lehrer')->select('id'))
            ->whereNotIn('id', DB::table('zeugnis_klasse_klassenlehrer')->select('klasse_id'))
            ->get(['id', 'klassenlehrer_id'])
            ->each(fn ($k) => DB::table('zeugnis_klasse_klassenlehrer')->insert([
                'klasse_id'   => $k->id,
                'lehrer_id'   => $k->klassenlehrer_id,
                'reihenfolge' => 0,
                'created_at'  => $jetzt,
                'updated_at'  => $jetzt,
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('zeugnis_klasse_klassenlehrer');
    }
};
