<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Les questions viennent de plusieurs administrateurs. Savoir qui a
            // propose quoi est le minimum pour pouvoir moderer : sans auteur,
            // une question douteuse n'a personne a qui etre renvoyee.
            $table->string('propose_par')->nullable();
            $table->boolean('validee')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['propose_par', 'validee']);
        });
    }
};
