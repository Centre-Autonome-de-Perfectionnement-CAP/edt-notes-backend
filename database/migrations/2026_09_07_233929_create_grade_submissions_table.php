<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->unique()->constrained('evaluations')->cascadeOnDelete();
            $table->foreignId('enseignant_id')->constrained('users')->cascadeOnDelete();
            $table->enum('statut', ['soumis', 'archive'])->default('soumis');
            $table->string('qr_hash');
            $table->string('pdf_path')->nullable();
            $table->timestamp('date_soumission');
            $table->timestamp('date_archivage')->nullable();
            $table->foreignId('archive_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_submissions');
    }
};