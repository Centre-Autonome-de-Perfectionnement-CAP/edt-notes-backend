<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emploi_du_temps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filiere_id')->constrained('filieres')->cascadeOnDelete();
            $table->string('division')->default('RdivFC');
            $table->string('semestre');
            $table->date('date_debut_semaine');
            $table->date('date_fin_semaine');
            $table->text('observation')->nullable();
            $table->string('contact_responsable_nom')->nullable();
            $table->string('contact_responsable_tel')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emploi_du_temps');
    }
};