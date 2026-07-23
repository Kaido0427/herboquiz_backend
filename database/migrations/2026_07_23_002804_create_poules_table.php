<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom');                      // « Poule A »
            $table->unsignedSmallInteger('nb_qualifies')->default(4);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('equipe_poule', function (Blueprint $table) {
            // Pas de cle primaire propre : attach()/sync() n'inserent que les
            // deux cles etrangeres, une colonne id UUID resterait NULL.
            $table->foreignUuid('poule_id')->constrained('poules')->cascadeOnDelete();
            $table->foreignUuid('equipe_id')->constrained('equipes')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['poule_id', 'equipe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipe_poule');
        Schema::dropIfExists('poules');
    }
};
