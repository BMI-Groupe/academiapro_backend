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
		Schema::create('students', function (Blueprint $table) {
			$table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
			$table->string('first_name', 100);
			$table->string('last_name', 100);
			$table->string('matricule', 50)->unique();
			$table->date('birth_date')->nullable();
			$table->enum('gender', ['M', 'F'])->nullable();
			$table->string('address', 255)->nullable();
			$table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
			$table->foreignId('parent_user_id')->nullable()->constrained('users')->onDelete('set null'); // Lien parent
			$table->string('parent_contact')->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('students');
	}
};


