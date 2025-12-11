<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Teacher>
 */
class TeacherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->create(['role' => 'enseignant'])->id,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->phoneNumber(),
            'specialization' => fake()->randomElement(['Mathématiques', 'Français', 'Histoire-Géo', 'Anglais', 'Physique-Chimie', 'SVT', 'Sport', 'Philosophie']),
            'birth_date' => fake()->dateTimeBetween('-60 years', '-25 years')->format('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
