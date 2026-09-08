<?php
// database/migrations/2026_09_04_000003_create_notes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
            $table->decimal('valeur', 4, 2)->nullable();
            $table->boolean('absent')->default(false);
            $table->boolean('verrouille')->default(false);
            $table->timestamps();

            $table->unique(['evaluation_id', 'etudiant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};