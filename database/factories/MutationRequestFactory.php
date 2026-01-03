<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MutationRequest;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MutationRequest>
 */
class MutationRequestFactory extends Factory
{
    protected $model = MutationRequest::class;

    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'from_unit_id' => OrganizationUnit::factory(),
            'to_unit_id' => OrganizationUnit::factory(),
            'effective_date' => $this->faker->dateTimeBetween('now', '+3 months'),
            'reason' => $this->faker->sentence(),
            'status' => 'pending',
            'submitted_by' => User::factory(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'approved',
            'approved_by' => User::factory(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'rejected',
        ]);
    }
}
