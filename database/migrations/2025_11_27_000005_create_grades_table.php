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
		Schema::create('grades', function (Blueprint $table) {
			$table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
			$table->foreignId('student_id')->constrained('students')->onDelete('cascade');
			$table->foreignId('assignment_id')->constrained('assignments')->onDelete('cascade');
			$table->decimal('score', 5, 2)->nullable();
			$table->text('notes')->nullable();
			$table->foreignId('graded_by')->nullable()->constrained('teachers')->onDelete('set null'); // Enseignant
			$table->timestamp('graded_at')->nullable();
			$table->timestamps();
			// Removed unique constraint to allow multiple grades per student per assignment (retakes, etc.)
			// $table->unique(['student_id', 'assignment_id']);
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('grades');
	}
};
