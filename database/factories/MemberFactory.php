<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\OrganizationUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        $joinYear = fake()->numberBetween(2015, 2025);

        return [
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'birth_place' => fake()->city(),
            'birth_date' => fake()->date(),
            'job_title' => fake()->jobTitle(),
            'employment_type' => fake()->randomElement(['organik', 'tkwt']),
            'status' => 'aktif',
            'join_date' => fake()->date(),
            'organization_unit_id' => OrganizationUnit::factory(),
            'kta_number' => fake()->unique()->numerify('KTA-####'),
            'nra' => fake()->unique()->numerify('NRA-########'),
            'join_year' => $joinYear,
            'sequence_number' => fake()->numberBetween(1, 9999),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'resign',
        ]);
    }
}

