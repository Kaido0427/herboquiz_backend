<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Elimination manuelle d'une equipe (forfait, disqualification, no-show).
     *
     * Un instant plutot qu'un booleen : on garde QUAND et par QUI, comme le
     * reste du projet. Null = en course. Une equipe eliminee reste au
     * classement (barree) mais ne peut plus se qualifier.
     */
    public function up(): void
    {
        Schema::table('equipes', function (Blueprint $table) {
            $table->timestamp('elimine_le')->nullable()->after('active');
            $table->string('elimine_par')->nullable()->after('elimine_le');
        });
    }

    public function down(): void
    {
        Schema::table('equipes', function (Blueprint $table) {
            $table->dropColumn(['elimine_le', 'elimine_par']);
        });
    }
};
