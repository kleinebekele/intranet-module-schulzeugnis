<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Klassenstufen-Bereich (von–bis) je Schulstufe.
 *
 * Damit ordnet der Klassen-Import eine reine Klassenstufe (z. B. „5") der Schulstufe
 * zu, deren Bereich sie abdeckt – pflegbar unter „Schulstufen", statt im Code
 * hartcodiert. Die bekannten Standard-Stufen werden sinnvoll vorbelegt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnis_stufen', function (Blueprint $table) {
            $table->unsignedTinyInteger('von_klasse')->nullable()->after('reihenfolge');
            $table->unsignedTinyInteger('bis_klasse')->nullable()->after('von_klasse');
        });

        // Vorbelegung der Standard-Schulstufen – nur, wo noch nichts gepflegt ist.
        $standard = [
            'Primarstufe'      => [1, 4],
            'Sekundarstufe I'  => [5, 7],
            'Sekundarstufe II' => [8, 10],
            'Oberstufe'        => [11, 13],
        ];
        foreach ($standard as $name => [$von, $bis]) {
            DB::table('zeugnis_stufen')
                ->where('name', $name)
                ->whereNull('von_klasse')
                ->whereNull('bis_klasse')
                ->update(['von_klasse' => $von, 'bis_klasse' => $bis]);
        }
    }

    public function down(): void
    {
        Schema::table('zeugnis_stufen', function (Blueprint $table) {
            $table->dropColumn(['von_klasse', 'bis_klasse']);
        });
    }
};
