<?php

namespace Database\Factories;

use App\Models\UnionPosition;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnionPositionFactory extends Factory
{
    protected $model = UnionPosition::class;

    public function definition(): array
    {
        return [
            'name' => fake()->jobTitle(),
            'code' => strtoupper(fake()->bothify('???-###')),
        ];
    }
}
