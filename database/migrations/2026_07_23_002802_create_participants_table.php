<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom');
            $table->string('prenom')->nullable();
            // Le pseudo affiche dans le groupe Messenger : c'est LUI que
            // l'animateur reconnait en direct, pas l'etat civil.
            $table->string('pseudo')->nullable();
            $table->string('telephone')->nullable();
            $table->boolean('confirme')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
