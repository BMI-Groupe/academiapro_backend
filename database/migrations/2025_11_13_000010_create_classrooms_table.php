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
		// Table des modèles de classes (indépendante de l'année scolaire)
		Schema::create('classroom_templates', function (Blueprint $table) {
			$table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
			$table->string('name', 100); // ex: "6e", "5e", "Terminale"
			$table->string('code', 50); // ex: "6EME", "5EME"
			$table->enum('cycle', ['primaire', 'college', 'lycee']);
			$table->string('level', 50); // ex: "6e", "5e", "4e"
			$table->decimal('tuition_fee', 10, 2)->default(0); // Frais de base, peut être surchargé par section
			$table->boolean('is_active')->default(true);
			$table->timestamps();
			$table->index(['cycle', 'level']);
			$table->unique(['code', 'school_id']); // Un code unique par école
		});

		// Table des sections (instance d'une classe pour une année scolaire)
		Schema::create('sections', function (Blueprint $table) {
			$table->id();
			$table->foreignId('classroom_template_id')->constrained('classroom_templates')->onDelete('cascade');
			$table->foreignId('school_year_id')->constrained('school_years')->onDelete('cascade');
			$table->foreignId('school_id')->constrained('schools')->onDelete('cascade'); // Redundant but useful for scoping
			$table->string('name', 100)->nullable(); // ex: "6e A", "6e B" - optionnel pour différencier les groupes
			$table->string('code', 50); // ex: "6EME-A-2024-2025"
			$table->decimal('tuition_fee', 10, 2)->nullable(); // Peut surcharger celui du template
			$table->unsignedInteger('capacity')->nullable(); // Nombre max d'élèves
			$table->boolean('is_active')->default(true);
			$table->timestamps();
			$table->index(['classroom_template_id', 'school_year_id']);
			$table->unique(['code', 'school_year_id', 'school_id']); // Unique section code per school per year
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('sections');
		Schema::dropIfExists('classroom_templates');
	}
};


