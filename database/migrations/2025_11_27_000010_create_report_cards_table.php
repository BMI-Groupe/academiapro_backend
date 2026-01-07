<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('school_year_id')->constrained('school_years')->onDelete('cascade');
            $table->foreignId('section_id')->constrained('sections')->onDelete('cascade');
            $table->foreignId('assignment_id')->nullable()->constrained('assignments')->onDelete('cascade');
            $table->decimal('average', 5, 2)->nullable();
            $table->integer('rank')->nullable();
            $table->integer('period')->nullable();
            $table->integer('absences')->default(0);
            $table->text('comments')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            
            // Unique constraint: un bulletin par devoir par élève
            $table->unique(['student_id', 'assignment_id'], 'student_assignment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_cards');
    }
};
