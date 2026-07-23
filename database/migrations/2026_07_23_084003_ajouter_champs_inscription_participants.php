<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('email')->nullable();
            $table->string('lien_facebook')->nullable();
            // Distingue une fiche saisie par un administrateur d'une inscription
            // faite par l'interesse lui-meme : les premieres sont incompletes
            // par nature et doivent pouvoir etre completees sans doublon.
            $table->boolean('auto_inscrit')->default(false);
            $table->timestamp('inscrit_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn(['email', 'lien_facebook', 'auto_inscrit', 'inscrit_le']);
        });
    }
};
