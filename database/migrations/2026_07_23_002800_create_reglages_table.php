<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Coeur de la regle « zero codage en dur » : textes de la page publique,
        // prix, dates, et TOUS les seuils qui pilotent la simulation du format
        // vivent ici et s'editent depuis l'administration.
        Schema::create('reglages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('cle')->unique();
            $table->text('valeur')->nullable();
            $table->string('type')->default('texte');   // texte, nombre, booleen, json, markdown
            $table->string('groupe')->default('general'); // general, annonce, prix, simulation, reglement
            $table->string('libelle');
            $table->text('aide')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglages');
    }
};
