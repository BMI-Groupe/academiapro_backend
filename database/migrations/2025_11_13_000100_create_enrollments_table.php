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
		Schema::create('enrollments', function (Blueprint $table) {
			$table->id();
			$table->foreignId('student_id')->constrained('students')->onDelete('cascade');
			$table->foreignId('classroom_id')->constrained('classrooms')->onDelete('cascade');
			$table->foreignId('school_year_id')->constrained('school_years')->onDelete('cascade');
			$table->date('enrolled_at')->nullable();
			$table->timestamps();
			$table->unique(['student_id', 'school_year_id']); // Un élève par année
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('enrollments');
	}
};


