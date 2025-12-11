<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('type'); // Devoir, Examen, Composition, etc.
            $table->decimal('max_score', 5, 2)->default(20.00); // Gardé pour compatibilité, mais on utilise passing/total
            $table->decimal('passing_score', 5, 2)->default(10.00);
            $table->decimal('total_score', 5, 2)->default(20.00);
            $table->date('start_date')->nullable();
            $table->date('due_date');
            $table->foreignId('classroom_id')->constrained('classrooms')->onDelete('cascade');
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->onDelete('cascade');
            $table->foreignId('school_year_id')->constrained('school_years')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null'); // Director
            $table->integer('period')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
