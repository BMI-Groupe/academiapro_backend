<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('subjects', function (Blueprint $table) {
			$table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
			$table->string('name', 150);
			$table->string('code', 50);
            $table->foreignId('school_year_id')->nullable()->constrained()->onDelete('cascade');
            $table->integer('coefficient')->default(1);
            $table->unique(['school_id', 'code', 'school_year_id'], 'subjects_school_code_year_unique');
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('subjects');
	}
};


