<?php

namespace Database\Factories;

use App\Models\Instructor;
use App\Models\InstructorSkill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstructorSkill>
 */
class InstructorSkillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'instructor_id' => Instructor::factory(),
            'skill' => fake()->randomElement(['Microsoft Excel', 'Microsoft Word', 'Microsoft PowerPoint', 'Desain Grafis', 'Web Programming']),
            'level' => fake()->randomElement(['beginner', 'intermediate', 'advanced']),
        ];
    }
}
