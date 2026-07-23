<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // L'entite qui concourt est TOUJOURS une equipe, meme en solo (equipe
        // d'une personne). C'est ce qui permet de basculer solo -> duo apres
        // les inscriptions sans rien reecrire ni perdre de donnees.
        Schema::create('equipes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom')->nullable();          // calcule si vide
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('equipe_participant', function (Blueprint $table) {
            // Pas de cle primaire propre : attach()/sync() n'inserent que les
            // deux cles etrangeres, une colonne id UUID resterait NULL.
            $table->foreignUuid('equipe_id')->constrained('equipes')->cascadeOnDelete();
            $table->foreignUuid('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['equipe_id', 'participant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipe_participant');
        Schema::dropIfExists('equipes');
    }
};
