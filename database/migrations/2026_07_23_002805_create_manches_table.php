<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('libelle');
            $table->string('type')->default('poule');   // poule | duel
            $table->foreignUuid('poule_id')->nullable()->constrained('poules')->nullOnDelete();
            $table->string('phase')->nullable();        // huitieme, quart, demie, petite_finale, finale
            $table->timestamp('date_prevue')->nullable();
            $table->unsignedSmallInteger('nb_questions_prevu')->default(15);
            // Duel : score a atteindre pour l'emporter. Null pour une poule,
            // ou l'on joue toutes les questions prevues.
            $table->unsignedSmallInteger('score_cible')->nullable();
            $table->string('statut')->default('a_venir'); // a_venir | en_cours | terminee
            $table->unsignedSmallInteger('question_courante')->default(0);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('equipe_manche', function (Blueprint $table) {
            // Pas de cle primaire propre : attach()/sync() n'inserent que les
            // deux cles etrangeres, une colonne id UUID resterait NULL.
            $table->foreignUuid('manche_id')->constrained('manches')->cascadeOnDelete();
            $table->foreignUuid('equipe_id')->constrained('equipes')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['manche_id', 'equipe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipe_manche');
        Schema::dropIfExists('manches');
    }
};
