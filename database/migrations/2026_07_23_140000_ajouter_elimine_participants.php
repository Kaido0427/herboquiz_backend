<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Elimination d'un PARTICIPANT (joueur), distincte de la suppression.
     *
     * Un joueur pas serieux : on l'ecarte de cette edition, mais il RESTE en
     * base (il pourra revenir une prochaine fois). Reversible : on le
     * reintegre quand on veut. C'est une elimination, pas une suppression.
     * Null = en course.
     */
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->timestamp('elimine_le')->nullable()->after('confirme');
            $table->string('elimine_par')->nullable()->after('elimine_le');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn(['elimine_le', 'elimine_par']);
        });
    }
};
