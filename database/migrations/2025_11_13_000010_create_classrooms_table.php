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
			$table->string('name', 100);
			$table->string('code', 50)->unique();
			$table->enum('cycle', ['primaire', 'college', 'lycee']);
			$table->string('level', 50);
			$table->timestamps();
			$table->index(['cycle', 'level']);
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


