<?php

namespace Database\Factories;

use App\Models\CashflowCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashflowCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'type' => fake()->randomElement(['income', 'expense']),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
