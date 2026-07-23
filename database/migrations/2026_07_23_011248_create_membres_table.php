<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Les personnes rattachees a un role. Le code reste partage ; cette
        // liste evite juste a chacun de retaper son nom a chaque connexion, et
        // surtout d'ecrire « Ekson » un soir et « Eckson » le lendemain — ce qui
        // rendrait la tracabilite des points inutilisable.
        Schema::create('membres', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom');
            $table->string('role')->default('admin');   // admin | modo
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membres');
    }
};
