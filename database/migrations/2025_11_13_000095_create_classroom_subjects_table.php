<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('section_subjects', function (Blueprint $table) {
			$table->id();
			$table->foreignId('section_id')->constrained('sections')->onDelete('cascade');
			$table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
			$table->foreignId('school_year_id')->constrained('school_years')->onDelete('cascade'); // Gardé pour index/performance
			$table->unsignedTinyInteger('coefficient')->default(1);
			$table->timestamps();

			$table->unique(['section_id', 'subject_id', 'school_year_id'], 'section_subj_year_unique');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('section_subjects');
	}
};

