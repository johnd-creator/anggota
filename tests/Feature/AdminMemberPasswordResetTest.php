<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AdminMemberPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_super_admin_can_reset_member_password_without_breaking_google_sso(): void
    {
        [$unit, $member, $targetUser] = $this->linkedMember([
            'google_id' => 'google-123',
            'microsoft_id' => 'microsoft-123',
        ]);

        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        DB::table('sessions')->insert([
            'id' => 'target-session',
            'user_id' => $targetUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);
        $targetUser->createToken('mobile-test');

        $response = $this->actingAs($superAdmin)->get(route('admin.members.reset_password.edit', $member));
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Members/ResetPassword')
            ->where('member.id', $member->id)
            ->where('member.user.has_google_sso', true)
        );

        $this->actingAs($superAdmin)
            ->post(route('admin.members.reset_password.update', $member), [
                'password' => 'new-secret-password',
                'password_confirmation' => 'new-secret-password',
            ])
            ->assertRedirect(route('admin.members.show', $member));

        $targetUser->refresh();
        $this->assertTrue(Hash::check('new-secret-password', $targetUser->password));
        $this->assertSame('google-123', $targetUser->google_id);
        $this->assertSame('microsoft-123', $targetUser->microsoft_id);
        $this->assertSame($member->id, $targetUser->member_id);
        $this->assertSame($unit->id, $targetUser->organization_unit_id);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $targetUser->id)->count());
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $superAdmin->id,
            'action' => 'member_password_reset',
            'subject_type' => User::class,
            'subject_id' => $targetUser->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $superAdmin->id,
            'event' => 'member_password_reset',
            'subject_type' => User::class,
            'subject_id' => $targetUser->id,
        ]);
    }

    public function test_login_manual_works_after_admin_reset_for_sso_user(): void
    {
        [, $member, $targetUser] = $this->linkedMember([
            'email' => 'sso-user@example.com',
            'password' => Hash::make('old-secret-password'),
            'google_id' => 'google-login-manual',
        ]);

        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.members.reset_password.update', $member), [
                'password' => 'manual-secret-password',
                'password_confirmation' => 'manual-secret-password',
            ])
            ->assertRedirect(route('admin.members.show', $member));

        auth()->logout();

        $this->post('/login', [
            'email' => $targetUser->email,
            'password' => 'manual-secret-password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($targetUser->fresh());
    }

    public function test_admin_unit_can_reset_own_unit_member_but_not_other_unit_member(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        [, $ownMember] = $this->linkedMember([], $unitA);
        [, $otherMember] = $this->linkedMember([], $unitB);

        $adminUnit = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $this->actingAs($adminUnit)
            ->post(route('admin.members.reset_password.update', $ownMember), [
                'password' => 'unit-secret-password',
                'password_confirmation' => 'unit-secret-password',
            ])
            ->assertRedirect(route('admin.members.show', $ownMember));

        $this->actingAs($adminUnit)
            ->get(route('admin.members.reset_password.edit', $otherMember))
            ->assertForbidden();

        $this->actingAs($adminUnit)
            ->post(route('admin.members.reset_password.update', $otherMember), [
                'password' => 'blocked-secret-password',
                'password_confirmation' => 'blocked-secret-password',
            ])
            ->assertForbidden();
    }

    public function test_admin_pusat_can_reset_member_across_units(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);
        [, $member, $targetUser] = $this->linkedMember([], $unitB);

        $adminPusat = User::factory()->create([
            'role_id' => Role::where('name', 'admin_pusat')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $this->actingAs($adminPusat)
            ->post(route('admin.members.reset_password.update', $member), [
                'password' => 'pusat-secret-password',
                'password_confirmation' => 'pusat-secret-password',
            ])
            ->assertRedirect(route('admin.members.show', $member));

        $this->assertTrue(Hash::check('pusat-secret-password', $targetUser->fresh()->password));
    }

    public function test_other_roles_cannot_reset_member_password(): void
    {
        [, $member, $targetUser] = $this->linkedMember();
        $originalPassword = $targetUser->password;

        foreach (['pengurus', 'anggota', 'reguler'] as $roleName) {
            $actor = User::factory()->create([
                'role_id' => Role::where('name', $roleName)->first()->id,
            ]);

            $response = $this->actingAs($actor)
                ->get(route('admin.members.reset_password.edit', $member));

            $this->assertContains($response->getStatusCode(), [302, 403]);

            $response = $this->actingAs($actor)
                ->post(route('admin.members.reset_password.update', $member), [
                    'password' => 'forbidden-secret-password',
                    'password_confirmation' => 'forbidden-secret-password',
                ]);

            $this->assertContains($response->getStatusCode(), [302, 403]);
            $this->assertSame($originalPassword, $targetUser->fresh()->password);
        }
    }

    public function test_member_without_linked_user_cannot_be_reset_and_no_user_is_created(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $member = Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'user_id' => null,
        ]);
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);
        $usersBefore = User::count();

        $this->actingAs($superAdmin)
            ->get(route('admin.members.reset_password.edit', $member))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->post(route('admin.members.reset_password.update', $member), [
                'password' => 'new-secret-password',
                'password_confirmation' => 'new-secret-password',
            ])
            ->assertForbidden();

        $this->assertSame($usersBefore, User::count());
    }

    protected function linkedMember(array $userOverrides = [], ?OrganizationUnit $unit = null): array
    {
        $unit ??= OrganizationUnit::factory()->create();

        $targetUser = User::factory()->create(array_merge([
            'role_id' => Role::where('name', 'anggota')->first()->id,
            'organization_unit_id' => $unit->id,
            'google_id' => 'google-'.fake()->uuid(),
            'microsoft_id' => 'microsoft-'.fake()->uuid(),
            'company_email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('old-secret-password'),
        ], $userOverrides));

        $member = Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'user_id' => $targetUser->id,
            'email' => $targetUser->email,
        ]);

        $targetUser->member_id = $member->id;
        $targetUser->save();

        return [$unit, $member, $targetUser];
    }
}
