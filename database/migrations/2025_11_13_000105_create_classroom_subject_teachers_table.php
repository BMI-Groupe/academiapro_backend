<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('section_subject_teachers', function (Blueprint $table) {
			$table->id();
			$table->foreignId('section_subject_id')->constrained('section_subjects')->onDelete('cascade');
			$table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
			$table->foreignId('school_year_id')->constrained('school_years')->onDelete('cascade'); // Gardé pour index/performance
			$table->timestamps();

			$table->unique(['section_subject_id', 'teacher_id', 'school_year_id'], 'section_subj_teach_unique');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('section_subject_teachers');
	}
};

