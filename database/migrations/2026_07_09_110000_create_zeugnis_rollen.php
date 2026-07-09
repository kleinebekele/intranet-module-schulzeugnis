<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Legt bei der Modul-Installation die drei Zeugnis-Rollen an, sofern sie noch
 * nicht existieren (idempotent, geprüft über die stabile role_id):
 *
 *   - Zeugnisadmin      – darf alle Unterseiten außer dem Designer
 *   - Zeugnismoderator  – darf alle Beurteilungen lesen und bearbeiten
 *   - Zeugnisdesigner   – darf nur das Zeugnis-Design/Format anpassen
 *
 * Die Rollen werden nur ANGELEGT. Welche Menüpunkte eine Rolle sehen darf,
 * bleibt bewusst Sache der Admin-Oberfläche (Modul-/Menüpunkt-Rechte) – so wie
 * bei allen anderen Rollen auch.
 *
 * is_system = true: die Rollen sind modul-gestellt und vor versehentlichem
 * Löschen geschützt (umbenennen und zuweisen bleibt möglich).
 */
return new class extends Migration
{
    /** @var array<int,array{role_id:string,name:string}> */
    private array $rollen = [
        ['role_id' => 'zeugnis_admin',     'name' => 'Zeugnisadmin'],
        ['role_id' => 'zeugnis_moderator', 'name' => 'Zeugnismoderator'],
        ['role_id' => 'zeugnis_designer',  'name' => 'Zeugnisdesigner'],
    ];

    public function up(): void
    {
        // Nur anlegen, wenn die roles-Tabelle des Cores vorhanden ist.
        if (! DB::getSchemaBuilder()->hasTable('roles')) {
            return;
        }

        $now = now();

        foreach ($this->rollen as $rolle) {
            $existiert = DB::table('roles')->where('role_id', $rolle['role_id'])->exists();
            if ($existiert) {
                continue;
            }

            DB::table('roles')->insert([
                'role_id'    => $rolle['role_id'],
                'name'       => $rolle['name'],
                'is_system'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('roles')) {
            return;
        }

        // Nur entfernen, wenn keine Benutzer-Zuweisung daran hängt (Historie schützen).
        foreach ($this->rollen as $rolle) {
            $hatZuweisung = DB::table('user_roles')->where('role_id', $rolle['role_id'])->exists();
            if ($hatZuweisung) {
                continue;
            }

            DB::table('roles')->where('role_id', $rolle['role_id'])->where('is_system', true)->delete();
        }
    }
};
