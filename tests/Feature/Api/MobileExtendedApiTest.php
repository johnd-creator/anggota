<?php

use App\Models\LetterCategory;
use App\Models\Member;
use App\Models\MobileDevice;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

function mobileExtendedToken(User $user): string
{
    return $user->createToken('extended-test')->plainTextToken;
}

function mobileExtendedUser(string $roleName, ?OrganizationUnit $unit = null): User
{
    $role = Role::where('name', $roleName)->firstOrFail();

    return User::factory()->create([
        'role_id' => $role->id,
        'organization_unit_id' => $unit?->id,
    ]);
}

test('extended mobile endpoints still require bearer auth', function () {
    $this->getJson('/api/mobile/v1/admin/members')->assertUnauthorized();
    $this->getJson('/api/mobile/v1/letters/inbox')->assertUnauthorized();
    $this->getJson('/api/mobile/v1/finance/ledgers')->assertUnauthorized();
});

test('mobile admin member list is scoped to admin unit', function () {
    $unit = OrganizationUnit::factory()->create();
    $otherUnit = OrganizationUnit::factory()->create();
    $admin = mobileExtendedUser('admin_unit', $unit);
    $ownMember = Member::factory()->create(['organization_unit_id' => $unit->id, 'full_name' => 'Own Unit Member']);
    $otherMember = Member::factory()->create(['organization_unit_id' => $otherUnit->id, 'full_name' => 'Other Unit Member']);

    $this->withHeader('Authorization', 'Bearer '.mobileExtendedToken($admin))
        ->getJson('/api/mobile/v1/admin/members')
        ->assertOk()
        ->assertJsonFragment(['full_name' => $ownMember->full_name])
        ->assertJsonMissing(['full_name' => $otherMember->full_name]);
});

test('mobile letter creator can create draft and read outbox', function () {
    $unit = OrganizationUnit::factory()->create();
    $admin = mobileExtendedUser('admin_unit', $unit);
    $category = LetterCategory::create([
        'name' => 'Undangan',
        'code' => 'UND',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $headers = ['Authorization' => 'Bearer '.mobileExtendedToken($admin)];

    $this->postJson('/api/mobile/v1/letters', [
        'letter_category_id' => $category->id,
        'signer_type' => 'ketua',
        'to_type' => 'external',
        'to_external_name' => 'Mitra Eksternal',
        'subject' => 'Undangan rapat',
        'body' => '<p>Isi surat</p>',
        'confidentiality' => 'normal',
        'urgency' => 'normal',
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('letter.status', 'draft')
        ->assertJsonPath('letter.from_unit.id', $unit->id);

    $this->getJson('/api/mobile/v1/letters/outbox', $headers)
        ->assertOk()
        ->assertJsonFragment(['subject' => 'Undangan rapat']);
});

test('mobile devices can be registered and removed by owner only', function () {
    $user = mobileExtendedUser('anggota');
    $other = mobileExtendedUser('anggota');
    $headers = ['Authorization' => 'Bearer '.mobileExtendedToken($user)];

    $deviceId = $this->postJson('/api/mobile/v1/devices', [
        'platform' => 'android',
        'device_token' => 'fcm-test-token',
        'device_name' => 'Pixel Test',
        'app_version' => '1.0.0',
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('device.user_id', $user->id)
        ->json('device.id');

    $this->app['auth']->forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.mobileExtendedToken($other))
        ->deleteJson('/api/mobile/v1/devices/'.$deviceId)
        ->assertNotFound();

    $this->app['auth']->forgetGuards();
    $this->deleteJson('/api/mobile/v1/devices/'.$deviceId, [], $headers)->assertOk();
    expect(MobileDevice::count())->toBe(0);
});

test('mobile oauth token exchange endpoints are explicit safe stubs', function () {
    $this->postJson('/api/mobile/v1/auth/google/token', ['id_token' => 'dummy'])
        ->assertStatus(501)
        ->assertJsonPath('provider', 'google');

    $this->postJson('/api/mobile/v1/auth/microsoft/token', ['id_token' => 'dummy'])
        ->assertStatus(501)
        ->assertJsonPath('provider', 'microsoft');
});
