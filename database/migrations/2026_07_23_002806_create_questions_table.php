<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Les questions sont preparees et saisies AVANT le match : c'est ce qui
        // permet a l'animateur de n'avoir qu'un geste a faire le jour venu.
        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('manche_id')->nullable()->constrained('manches')->nullOnDelete();
            $table->text('texte');
            $table->text('reponse');
            $table->text('indice')->nullable();
            $table->string('theme')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamp('posee_le')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
