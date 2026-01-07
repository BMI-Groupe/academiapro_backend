<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('classroom_template_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_template_id')->constrained('classroom_templates')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('school_year_id')->nullable()->constrained('school_years')->onDelete('cascade');
            $table->integer('coefficient')->default(1);
            $table->timestamps();
            
            // Une matière peut être assignée à un template pour différentes années scolaires
            $table->unique(['classroom_template_id', 'subject_id', 'school_year_id'], 'template_subjects_year_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classroom_template_subjects');
    }
};
