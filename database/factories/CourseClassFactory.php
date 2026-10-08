<?php

namespace Database\Factories;

use App\Models\CourseClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseClass>
 */
class CourseClassFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subject = fake()->randomElement(['Microsoft Excel', 'Microsoft Word', 'Microsoft PowerPoint', 'Desain Grafis', 'Web Programming']);

        return [
            'name' => $subject.' - Batch '.fake()->numberBetween(1, 20),
            'subject' => $subject,
            'student_count' => fake()->numberBetween(8, 25),
        ];
    }
}
