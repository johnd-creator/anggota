<?php

namespace Database\Factories;

use App\Models\Letter;
use App\Models\LetterCategory;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Letter>
 */
class LetterFactory extends Factory
{
    protected $model = Letter::class;

    public function definition(): array
    {
        return [
            'creator_user_id' => User::factory(),
            'from_unit_id' => OrganizationUnit::factory(),
            'letter_category_id' => fn() => LetterCategory::firstOrCreate(
                ['code' => 'UND'],
                ['name' => 'Undangan', 'is_active' => true]
            )->id,
            'signer_type' => 'ketua',
            'signer_type_secondary' => null,
            'to_type' => 'admin_pusat',
            'to_unit_id' => null,
            'to_member_id' => null,
            'subject' => $this->faker->sentence(),
            'body' => $this->faker->paragraphs(3, true),
            'confidentiality' => 'biasa',
            'urgency' => 'biasa',
            'status' => 'draft',
        ];
    }

    /**
     * Letter in submitted state.
     */
    public function submitted(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    /**
     * Letter requiring two-step approval (bendahara as secondary).
     */
    public function twoStepApproval(): static
    {
        return $this->state(fn(array $attributes) => [
            'signer_type_secondary' => 'bendahara',
        ]);
    }

    /**
     * Letter with sekretaris as primary signer.
     */
    public function signerSekretaris(): static
    {
        return $this->state(fn(array $attributes) => [
            'signer_type' => 'sekretaris',
        ]);
    }

    /**
     * Letter with primary approval already done.
     */
    public function primaryApproved(int $approverId): static
    {
        return $this->state(fn(array $attributes) => [
            'approved_by_user_id' => $approverId,
            'approved_primary_at' => now(),
        ]);
    }
}
