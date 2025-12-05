<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('classroom_subject_teachers', function (Blueprint $table) {
			$table->id();
			$table->foreignId('classroom_subject_id')->constrained('classroom_subjects')->onDelete('cascade');
			$table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
			$table->foreignId('school_year_id')->constrained('school_years')->onDelete('cascade');
			$table->timestamps();

			$table->unique(['classroom_subject_id', 'teacher_id', 'school_year_id'], 'cls_subj_teach_unique');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('classroom_subject_teachers');
	}
};

