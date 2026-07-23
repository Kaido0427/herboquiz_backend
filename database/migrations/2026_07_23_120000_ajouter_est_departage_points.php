<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un « barrage » departage une egalite PARFAITE (meme total ET meme
     * instant, quand la rapidite ne tranche plus). On l'enregistre comme une
     * ligne du journal, a points = 0 : il ne change donc aucun total (ni le
     * classement general, ni le prix du meilleur marqueur), il ne fait que
     * placer le vainqueur devant a total egal. Marque a part pour rester
     * tracable et pour ne pas etre pris pour un vrai point.
     */
    public function up(): void
    {
        Schema::table('points', function (Blueprint $table) {
            $table->boolean('est_departage')->default(false)->after('points');
        });
    }

    public function down(): void
    {
        Schema::table('points', function (Blueprint $table) {
            $table->dropColumn('est_departage');
        });
    }
};
