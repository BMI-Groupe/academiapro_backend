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
		Schema::create('classrooms', function (Blueprint $table) {
			$table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
			$table->string('name', 100);
			$table->string('code', 50);
			$table->enum('cycle', ['primaire', 'college', 'lycee']);
			$table->string('level', 50);
			$table->decimal('tuition_fee', 10, 2)->default(0);
			$table->foreignId('school_year_id')->constrained()->onDelete('cascade');
			$table->timestamps();
			$table->index(['cycle', 'level']);
			$table->unique(['code', 'school_year_id']);
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('classrooms');
	}
};


