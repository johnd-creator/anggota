<?php

namespace Database\Factories;

use App\Models\Aspiration;
use App\Models\AspirationCategory;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AspirationFactory extends Factory
{
    protected $model = Aspiration::class;

    public function definition()
    {
        return [
            'title' => $this->faker->sentence,
            'body' => $this->faker->paragraph,
            'status' => $this->faker->randomElement(['new', 'in_progress', 'resolved']),
            'support_count' => 0,
            'organization_unit_id' => OrganizationUnit::factory(),
            'member_id' => Member::factory(),
            'user_id' => User::factory(),
            'category_id' => AspirationCategory::factory(),
        ];
    }
}
