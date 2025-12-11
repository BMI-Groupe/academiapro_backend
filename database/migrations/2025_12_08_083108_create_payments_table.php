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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // Cashier
            $table->foreignId('school_year_id')->constrained('school_years')->onDelete('cascade');
            
            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->string('type')->default('TUITION'); // 'TUITION', 'REGISTRATION', 'OTHER'
            $table->string('reference')->unique()->nullable(); // Receipt number
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
