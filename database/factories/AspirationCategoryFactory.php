<?php

namespace Database\Factories;

use App\Models\AspirationCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class AspirationCategoryFactory extends Factory
{
    protected $model = AspirationCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
        ];
    }
}

