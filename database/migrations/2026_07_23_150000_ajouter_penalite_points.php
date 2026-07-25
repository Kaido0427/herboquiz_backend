<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Points de penalite.
     *
     * Une penalite est une ligne du journal a points NEGATIFS (ex : -10),
     * marquee `est_penalite`, avec un motif. Elle est sommee comme les autres :
     * elle fait donc baisser le total au classement, et reste tracable (qui,
     * quand, pourquoi). Distincte d'un vrai point marque pour l'affichage et
     * pour le calcul du meilleur marqueur.
     */
    public function up(): void
    {
        Schema::table('points', function (Blueprint $table) {
            $table->boolean('est_penalite')->default(false)->after('est_departage');
            $table->string('motif')->nullable()->after('est_penalite');
            // Une penalite peut viser un JOUEUR precis (et pas seulement l'equipe).
            // L'effet reste sur le total de l'equipe (le score est par equipe),
            // mais on garde qui, individuellement, l'a values.
            $table->foreignUuid('participant_id')->nullable()->after('equipe_id')
                ->constrained('participants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('points', function (Blueprint $table) {
            $table->dropColumn(['est_penalite', 'motif']);
        });
    }
};
