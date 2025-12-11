<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(['M', 'F']);
        return [
            'first_name' => fake()->firstName($gender === 'M' ? 'male' : 'female'),
            'last_name' => fake()->lastName(),
            'matricule' => 'STU' . fake()->unique()->numberBetween(10000, 99999),
            'birth_date' => fake()->dateTimeBetween('-18 years', '-10 years')->format('Y-m-d'),
            'gender' => $gender,
            'address' => fake()->address(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
