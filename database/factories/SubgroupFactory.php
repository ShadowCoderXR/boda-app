<?php

namespace Database\Factories;

use App\Models\Subgroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subgroup>
 */
class SubgroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Familia ' . $this->faker->lastName(),
            'user_id' => \App\Models\User::factory(),
        ];
    }
}
