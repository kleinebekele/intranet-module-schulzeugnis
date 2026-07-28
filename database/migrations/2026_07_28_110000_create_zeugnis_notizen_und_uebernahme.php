<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notizen als append-only Liste (Randspalte der Editoren) statt des bisherigen
 * Einzel-Überschreib-Felds `notiz` an Abschnitt und Klassentext.
 *
 * Bestehende Notiz-Inhalte werden als erste Zeile übernommen (Autor unbekannt →
 * „(übernommen)"), danach fallen die alten Spalten weg. down() legt die Spalten
 * LEER wieder an – die Inhalte sind dann nicht rekonstruierbar, sie leben aber
 * als kopierte Zeilen in `zeugnis_notizen` weiter.
 *
 * Autor als eingefrorener Schnappschuss (wie im Protokoll), autor_user_id als
 * loser Wert ohne FK – Insel-Prinzip des Moduls.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zeugnis_notizen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abschnitt_id')->nullable()->constrained('zeugnis_abschnitte')->cascadeOnDelete();
            $table->foreignId('klassentext_id')->nullable()->constrained('zeugnis_fach_klassentexte')->cascadeOnDelete();
            $table->unsignedBigInteger('autor_user_id')->nullable();
            $table->string('autor_name');
            $table->text('text');
            $table->timestamp('created_at')->nullable(); // append-only: kein updated_at
        });

        foreach (DB::table('zeugnis_abschnitte')->whereNotNull('notiz')->where('notiz', '!=', '')->get(['id', 'notiz', 'updated_at']) as $r) {
            DB::table('zeugnis_notizen')->insert([
                'abschnitt_id' => $r->id,
                'autor_name'   => '(übernommen)',
                'text'         => $r->notiz,
                'created_at'   => $r->updated_at ?? now(),
            ]);
        }
        foreach (DB::table('zeugnis_fach_klassentexte')->whereNotNull('notiz')->where('notiz', '!=', '')->get(['id', 'notiz', 'updated_at']) as $r) {
            DB::table('zeugnis_notizen')->insert([
                'klassentext_id' => $r->id,
                'autor_name'     => '(übernommen)',
                'text'           => $r->notiz,
                'created_at'     => $r->updated_at ?? now(),
            ]);
        }

        Schema::table('zeugnis_abschnitte', fn (Blueprint $table) => $table->dropColumn('notiz'));
        Schema::table('zeugnis_fach_klassentexte', fn (Blueprint $table) => $table->dropColumn('notiz'));
    }

    public function down(): void
    {
        Schema::table('zeugnis_abschnitte', fn (Blueprint $table) => $table->text('notiz')->nullable());
        Schema::table('zeugnis_fach_klassentexte', fn (Blueprint $table) => $table->longText('notiz')->nullable());
        Schema::dropIfExists('zeugnis_notizen');
    }
};
