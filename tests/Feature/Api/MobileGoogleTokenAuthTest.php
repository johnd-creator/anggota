<?php

use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

function mobileGoogleUser(string $roleName = 'anggota', ?OrganizationUnit $unit = null, ?string $memberStatus = 'aktif'): array
{
    $unit ??= OrganizationUnit::factory()->create();
    $role = Role::where('name', $roleName)->firstOrFail();

    $user = User::factory()->create([
        'password' => Hash::make('secret-password'),
        'role_id' => $role->id,
        'organization_unit_id' => $unit->id,
    ]);

    $member = null;

    if ($memberStatus !== null) {
        $member = Member::factory()->create([
            'user_id' => $user->id,
            'organization_unit_id' => $unit->id,
            'email' => $user->email,
            'status' => $memberStatus,
            'join_date' => now()->subMonths(2)->toDateString(),
        ]);

        $user->forceFill(['member_id' => $member->id])->save();
    }

    return [$user->fresh(), $member?->fresh(), $unit];
}

test('mobile google token login returns bearer token and supports me plus logout', function () {
    [$user, $member, $unit] = mobileGoogleUser();

    $this->mock(GoogleIdTokenVerifier::class, function ($mock) use ($user) {
        $mock->shouldReceive('verify')
            ->once()
            ->with('valid-google-id-token')
            ->andReturn([
                'sub' => 'google-user-123',
                'email' => $user->email,
                'email_verified' => true,
                'name' => $user->name,
                'aud' => 'flutter-client-id',
                'iss' => 'https://accounts.google.com',
                'exp' => now()->addHour()->timestamp,
            ]);
    });

    $response = $this->postJson('/api/mobile/v1/auth/google/token', [
        'id_token' => 'valid-google-id-token',
        'device_name' => 'android-test',
    ]);

    $response->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.role.name', 'anggota')
        ->assertJsonPath('user.member.id', $member->id)
        ->assertJsonPath('user.member.organization_unit.id', $unit->id);

    $token = $response->json('access_token');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/mobile/v1/me')
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.member.id', $member->id);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/mobile/v1/auth/logout')
        ->assertOk()
        ->assertJsonPath('status', 'ok');

    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/mobile/v1/me')
        ->assertUnauthorized();

    expect(PersonalAccessToken::count())->toBe(0);
});

test('mobile google token login rejects invalid token payloads', function () {
    $this->mock(GoogleIdTokenVerifier::class, function ($mock) {
        $mock->shouldReceive('verify')
            ->once()
            ->with('bad-google-id-token')
            ->andThrow(ValidationException::withMessages([
                'id_token' => ['Token Google tidak valid.'],
            ]));
    });

    $this->postJson('/api/mobile/v1/auth/google/token', [
        'id_token' => 'bad-google-id-token',
        'device_name' => 'android-test',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['id_token']);

    expect(PersonalAccessToken::count())->toBe(0);
});

test('mobile google token login rejects unverified email payloads', function () {
    $this->mock(GoogleIdTokenVerifier::class, function ($mock) {
        $mock->shouldReceive('verify')
            ->once()
            ->with('unverified-google-id-token')
            ->andThrow(ValidationException::withMessages([
                'id_token' => ['Email Google belum terverifikasi.'],
            ]));
    });

    $this->postJson('/api/mobile/v1/auth/google/token', [
        'id_token' => 'unverified-google-id-token',
        'device_name' => 'android-test',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['id_token']);

    expect(PersonalAccessToken::count())->toBe(0);
});

test('mobile google token login rejects emails that are not registered locally', function () {
    $this->mock(GoogleIdTokenVerifier::class, function ($mock) {
        $mock->shouldReceive('verify')
            ->once()
            ->with('registered-check-token')
            ->andReturn([
                'sub' => 'google-user-999',
                'email' => 'not-registered@example.com',
                'email_verified' => true,
                'name' => 'Tidak Terdaftar',
                'aud' => 'flutter-client-id',
                'iss' => 'https://accounts.google.com',
                'exp' => now()->addHour()->timestamp,
            ]);
    });

    $this->postJson('/api/mobile/v1/auth/google/token', [
        'id_token' => 'registered-check-token',
        'device_name' => 'android-test',
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Akun Google ini belum terdaftar di sistem.');

    expect(PersonalAccessToken::count())->toBe(0);
});

test('mobile google token login rejects inactive member accounts', function () {
    [$user] = mobileGoogleUser('anggota', null, 'resign');

    $this->mock(GoogleIdTokenVerifier::class, function ($mock) use ($user) {
        $mock->shouldReceive('verify')
            ->once()
            ->with('inactive-member-token')
            ->andReturn([
                'sub' => 'google-user-321',
                'email' => $user->email,
                'email_verified' => true,
                'name' => $user->name,
                'aud' => 'flutter-client-id',
                'iss' => 'https://accounts.google.com',
                'exp' => now()->addHour()->timestamp,
            ]);
    });

    $this->postJson('/api/mobile/v1/auth/google/token', [
        'id_token' => 'inactive-member-token',
        'device_name' => 'android-test',
    ])
        ->assertStatus(403)
        ->assertJsonPath('message', 'Login Google tidak dapat digunakan untuk akun ini.');

    expect(PersonalAccessToken::count())->toBe(0);
});

test('mobile google token login rejects users without mobile access rights', function () {
    [$user] = mobileGoogleUser('reguler', null, null);

    $this->mock(GoogleIdTokenVerifier::class, function ($mock) use ($user) {
        $mock->shouldReceive('verify')
            ->once()
            ->with('regular-user-token')
            ->andReturn([
                'sub' => 'google-user-654',
                'email' => $user->email,
                'email_verified' => true,
                'name' => $user->name,
                'aud' => 'flutter-client-id',
                'iss' => 'https://accounts.google.com',
                'exp' => now()->addHour()->timestamp,
            ]);
    });

    $this->postJson('/api/mobile/v1/auth/google/token', [
        'id_token' => 'regular-user-token',
        'device_name' => 'android-test',
    ])
        ->assertStatus(403)
        ->assertJsonPath('message', 'Login Google tidak dapat digunakan untuk akun ini.');

    expect(PersonalAccessToken::count())->toBe(0);
});
