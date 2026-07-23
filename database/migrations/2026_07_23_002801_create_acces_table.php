<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un seul code par role, communique tel quel aux interesses. Le code
        // animateur est regenerable : les modos changent d'un match a l'autre,
        // et regenerer doit reellement revoquer les jetons deja distribues.
        Schema::create('acces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('role')->unique();           // admin | modo
            $table->string('code_hash');
            $table->string('code_clair')->nullable();   // lisible par un admin pour le communiquer
            $table->timestamp('regenere_le')->nullable();
            $table->timestamps();
        });

        // Journal des connexions : qui s'est connecte sous quel nom, et depuis
        // quand. C'est ce qui rend l'attribution des points tracable malgre le
        // code partage.
        Schema::create('sessions_acces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('role');
            $table->string('nom');                      // saisi a la connexion
            $table->timestamp('derniere_activite')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions_acces');
        Schema::dropIfExists('acces');
    }
};
