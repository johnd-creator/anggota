<?php

namespace Database\Factories;

use App\Models\PendingMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PendingMemberFactory extends Factory
{
    protected $model = PendingMember::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'organization_unit_id' => null,
            'notes' => null,
            'status' => 'pending',
            'reviewer_id' => null,
        ];
    }
}
