<?php

namespace Database\Factories;

use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'user_id' => 1, // Admin par défaut pour simplifier, ou User::factory()
            'school_year_id' => 1, // Sera écrasé par le seeder
            'amount' => fake()->randomFloat(0, 5000, 50000), // Montants ronds si possible ? fake float donne des décimales
            'payment_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'type' => fake()->randomElement(['TUITION', 'REGISTRATION']),
            'reference' => 'PAY-' . fake()->unique()->numerify('#####'),
            'notes' => fake()->optional()->sentence(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
