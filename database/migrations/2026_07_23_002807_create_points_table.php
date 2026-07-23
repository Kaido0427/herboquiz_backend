<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Journal en AJOUT SEUL. Le classement se calcule en sommant cette
        // table ; aucun total n'est stocke ailleurs. On obtient ainsi
        // gratuitement l'annulation, l'historique, et surtout la tracabilite :
        // on sait toujours QUI a attribue quel point et quand.
        Schema::create('points', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('manche_id')->constrained('manches')->cascadeOnDelete();
            $table->foreignUuid('question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->foreignUuid('equipe_id')->constrained('equipes')->cascadeOnDelete();
            $table->integer('points')->default(1);
            $table->string('attribue_par');             // nom saisi a la connexion
            $table->string('role_auteur');              // admin | modo
            $table->timestamp('annule_le')->nullable();
            $table->string('annule_par')->nullable();
            $table->timestamps();
            $table->index(['manche_id', 'annule_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points');
    }
};
