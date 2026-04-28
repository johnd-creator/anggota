<?php

use App\Models\Aspiration;
use App\Models\AspirationCategory;
use App\Models\AspirationSupport;
use App\Models\Announcement;
use App\Models\AnnouncementDismissal;
use App\Models\ActivityLog;
use App\Models\DuesPayment;
use App\Models\Member;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

function mobileUserWithMember(?OrganizationUnit $unit = null): array
{
    $unit ??= OrganizationUnit::factory()->create();
    $role = Role::where('name', 'anggota')->firstOrFail();
    $user = User::factory()->create([
        'password' => Hash::make('secret-password'),
        'role_id' => $role->id,
        'organization_unit_id' => $unit->id,
    ]);
    $member = Member::factory()->create([
        'user_id' => $user->id,
        'organization_unit_id' => $unit->id,
        'email' => $user->email,
        'join_date' => now()->subMonths(2)->toDateString(),
    ]);
    $user->forceFill(['member_id' => $member->id])->save();

    return [$user->fresh(), $member->fresh(), $unit];
}

function mobileToken(User $user): string
{
    return $user->createToken('test-device')->plainTextToken;
}

test('mobile login returns bearer token and user payload', function () {
    [$user, $member, $unit] = mobileUserWithMember();

    $response = $this->postJson('/api/mobile/v1/auth/login', [
        'email' => $user->email,
        'password' => 'secret-password',
        'device_name' => 'android-test',
    ]);

    $response->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.role.name', 'anggota')
        ->assertJsonPath('user.member.id', $member->id)
        ->assertJsonPath('user.member.organization_unit.id', $unit->id)
        ->assertJsonStructure(['access_token']);

    expect(PersonalAccessToken::count())->toBe(1);
});

test('mobile login rejects invalid password', function () {
    [$user] = mobileUserWithMember();

    $response = $this->postJson('/api/mobile/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Email atau password salah.');

    expect(PersonalAccessToken::count())->toBe(0);
});

test('mobile endpoints require bearer token', function () {
    $this->getJson('/api/mobile/v1/profile')->assertUnauthorized();
});

test('me profile and dues use the authenticated user only', function () {
    [$user, $member] = mobileUserWithMember();
    [$otherUser, $otherMember] = mobileUserWithMember();

    DuesPayment::create([
        'member_id' => $member->id,
        'organization_unit_id' => $member->organization_unit_id,
        'period' => now()->format('Y-m'),
        'status' => 'paid',
        'amount' => 30000,
        'paid_at' => now(),
    ]);
    DuesPayment::create([
        'member_id' => $otherMember->id,
        'organization_unit_id' => $otherMember->organization_unit_id,
        'period' => now()->format('Y-m'),
        'status' => 'unpaid',
        'amount' => 999999,
    ]);

    Notification::create([
        'id' => (string) Str::uuid(),
        'type' => 'dues',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'message' => 'Iuran Anda tercatat',
        'data' => ['category' => 'dues'],
    ]);
    Notification::create([
        'id' => (string) Str::uuid(),
        'type' => 'dues',
        'notifiable_type' => User::class,
        'notifiable_id' => $otherUser->id,
        'message' => 'Pesan user lain',
        'data' => ['category' => 'dues'],
    ]);

    $headers = ['Authorization' => 'Bearer '.mobileToken($user)];

    $this->getJson('/api/mobile/v1/me', $headers)
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.member.id', $member->id);

    $this->getJson('/api/mobile/v1/profile', $headers)
        ->assertOk()
        ->assertJsonPath('member.id', $member->id)
        ->assertJsonMissing(['id' => $otherMember->id]);

    $this->getJson('/api/mobile/v1/dues', $headers)
        ->assertOk()
        ->assertJsonPath('has_member', true)
        ->assertJsonMissing(['amount' => 999999]);

    $this->getJson('/api/mobile/v1/notifications', $headers)
        ->assertOk()
        ->assertJsonFragment(['message' => 'Iuran Anda tercatat'])
        ->assertJsonMissing(['message' => 'Pesan user lain']);
});

test('mobile user without linked member gets safe empty profile and dues response', function () {
    $role = Role::where('name', 'reguler')->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $headers = ['Authorization' => 'Bearer '.mobileToken($user)];

    $this->getJson('/api/mobile/v1/profile', $headers)
        ->assertOk()
        ->assertJsonPath('member', null)
        ->assertJsonPath('update_requests', []);

    $this->getJson('/api/mobile/v1/dues', $headers)
        ->assertOk()
        ->assertJsonPath('has_member', false)
        ->assertJsonPath('payments', []);
});

test('profile update request is created for authenticated member', function () {
    [$user, $member] = mobileUserWithMember();

    $response = $this->withHeader('Authorization', 'Bearer '.mobileToken($user))
        ->patchJson('/api/mobile/v1/profile/update-request', [
            'phone' => '081234567890',
            'address' => 'Alamat baru',
            'emergency_contact' => '081298765432',
            'company_join_date' => now()->subYear()->toDateString(),
            'notes' => 'Dari aplikasi mobile',
        ]);

    $response->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('update_request.member_id', $member->id);

    $this->assertDatabaseHas('member_update_requests', [
        'member_id' => $member->id,
        'status' => 'pending',
    ]);
});

test('document upload validates pdf only', function () {
    [$user] = mobileUserWithMember();
    Storage::fake('public');

    $response = $this->withHeader('Authorization', 'Bearer '.mobileToken($user))
        ->postJson('/api/mobile/v1/profile/documents', [
            'type' => 'ktp',
            'file' => UploadedFile::fake()->image('ktp.jpg'),
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

test('logout revokes current mobile token', function () {
    [$user] = mobileUserWithMember();
    $token = mobileToken($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/mobile/v1/auth/logout')
        ->assertOk()
        ->assertJsonPath('status', 'ok');

    expect(PersonalAccessToken::count())->toBe(0);
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/mobile/v1/me')
        ->assertUnauthorized();
});

test('mobile aspirations are scoped to authenticated user unit and can be created', function () {
    [$user, $member, $unit] = mobileUserWithMember();
    [$otherUser, $otherMember, $otherUnit] = mobileUserWithMember();
    $category = AspirationCategory::factory()->create(['name' => 'Fasilitas']);

    Aspiration::factory()->create([
        'member_id' => $member->id,
        'user_id' => $user->id,
        'organization_unit_id' => $unit->id,
        'category_id' => $category->id,
        'title' => 'Aspirasi unit sendiri',
    ]);
    Aspiration::factory()->create([
        'member_id' => $otherMember->id,
        'user_id' => $otherUser->id,
        'organization_unit_id' => $otherUnit->id,
        'category_id' => $category->id,
        'title' => 'Aspirasi unit lain',
    ]);

    $headers = ['Authorization' => 'Bearer '.mobileToken($user)];

    $this->getJson('/api/mobile/v1/aspirations', $headers)
        ->assertOk()
        ->assertJsonFragment(['title' => 'Aspirasi unit sendiri'])
        ->assertJsonMissing(['title' => 'Aspirasi unit lain']);

    $this->postJson('/api/mobile/v1/aspirations', [
        'category_id' => $category->id,
        'title' => 'Lampu ruang rapat',
        'body' => 'Mohon lampu ruang rapat unit diganti karena redup.',
        'tags' => ['Fasilitas', 'Rapat'],
        'is_anonymous' => false,
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('aspiration.title', 'Lampu ruang rapat')
        ->assertJsonPath('aspiration.organization_unit_id', $unit->id);

    $this->assertDatabaseHas('aspirations', [
        'title' => 'Lampu ruang rapat',
        'member_id' => $member->id,
        'organization_unit_id' => $unit->id,
    ]);
});

test('mobile utility endpoints expose config features and scoped lookups', function () {
    [$user, , $unit] = mobileUserWithMember();
    $headers = ['Authorization' => 'Bearer '.mobileToken($user)];
    AspirationCategory::factory()->create(['name' => 'Kesejahteraan']);

    $this->getJson('/api/mobile/v1/config', $headers)
        ->assertOk()
        ->assertJsonPath('api.version', 'v1')
        ->assertJsonPath('api.base_path', '/api/mobile/v1');

    $this->getJson('/api/mobile/v1/features', $headers)
        ->assertOk()
        ->assertJsonStructure(['features' => ['announcements', 'letters', 'finance', 'reports']]);

    $this->getJson('/api/mobile/v1/meta/lookups', $headers)
        ->assertOk()
        ->assertJsonPath('lookups.organization_units.0.id', $unit->id)
        ->assertJsonFragment(['name' => 'Kesejahteraan']);
});

test('mobile settings update profile password sessions and notification preferences', function () {
    [$user, $member] = mobileUserWithMember();
    $firstToken = mobileToken($user);
    $secondToken = mobileToken($user);
    $headers = ['Authorization' => 'Bearer '.$secondToken];

    $this->patchJson('/api/mobile/v1/settings/profile', ['name' => 'Nama Mobile'], $headers)
        ->assertOk()
        ->assertJsonPath('status', 'ok');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nama Mobile']);
    $this->assertDatabaseHas('members', ['id' => $member->id, 'full_name' => 'Nama Mobile']);

    $this->getJson('/api/mobile/v1/settings/sessions', $headers)
        ->assertOk()
        ->assertJsonCount(2, 'sessions');

    $this->postJson('/api/mobile/v1/settings/sessions/revoke-others', [], $headers)
        ->assertOk()
        ->assertJsonPath('count', 1);

    $this->patchJson('/api/mobile/v1/settings/notifications', [
        'channels' => [
            'mutations' => ['database'],
            'updates' => ['database'],
            'announcements' => ['database'],
            'dues' => ['database'],
            'reports' => [],
            'finance' => [],
        ],
        'digest_daily' => true,
    ], $headers)
        ->assertOk()
        ->assertJsonPath('notification_prefs.digest_daily', true);

    expect(NotificationPreference::where('user_id', $user->id)->exists())->toBeTrue();

    $this->patchJson('/api/mobile/v1/settings/password', [
        'current_password' => 'secret-password',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ], $headers)
        ->assertOk()
        ->assertJsonPath('status', 'ok');

    expect(Hash::check('new-secret-password', $user->fresh()->password))->toBeTrue();
    expect(PersonalAccessToken::where('tokenable_id', $user->id)->count())->toBe(1);
    expect(PersonalAccessToken::findToken($firstToken))->toBeNull();
});

test('mobile member card data requests and delete photo are user scoped', function () {
    [$user, $member] = mobileUserWithMember();
    $member->forceFill([
        'qr_token' => 'member-token-1',
        'card_valid_until' => now()->addYear()->toDateString(),
        'photo_path' => 'members/photos/old.jpg',
    ])->save();
    Storage::fake('public');
    Storage::disk('public')->put('members/photos/old.jpg', 'old');
    $headers = ['Authorization' => 'Bearer '.mobileToken($user)];

    $this->getJson('/api/mobile/v1/member/card', $headers)
        ->assertOk()
        ->assertJsonPath('card.member_id', $member->id)
        ->assertJsonPath('card.qr_token', 'member-token-1')
        ->assertJsonPath('card.has_qr', true)
        ->assertJsonPath('card.can_download_pdf', true)
        ->assertJsonPath('card.download_url', route('api.mobile.member.card.pdf'))
        ->assertJsonPath('card.verify_api_url', route('api.mobile.member.card.verify', 'member-token-1'));

    $this->deleteJson('/api/mobile/v1/profile/photo', [], $headers)
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('member.photo_url', null);

    Storage::disk('public')->assertMissing('members/photos/old.jpg');

    $this->postJson('/api/mobile/v1/member/data/export-request', [], $headers)
        ->assertOk()
        ->assertJsonPath('status', 'ok');

    $this->postJson('/api/mobile/v1/member/data/delete-request', [], $headers)
        ->assertOk()
        ->assertJsonPath('status', 'ok');

    expect(ActivityLog::where('actor_id', $user->id)->where('action', 'gdpr_export_request')->exists())->toBeTrue();
    expect(ActivityLog::where('actor_id', $user->id)->where('action', 'gdpr_delete_request')->exists())->toBeTrue();
});

test('mobile member card auto issues qr token and can download pdf', function () {
    [$user, $member] = mobileUserWithMember();
    $member->forceFill([
        'qr_token' => null,
        'card_valid_until' => null,
    ])->save();
    $headers = ['Authorization' => 'Bearer '.mobileToken($user)];

    $this->getJson('/api/mobile/v1/member/card', $headers)
        ->assertOk()
        ->assertJsonPath('card.member_id', $member->id)
        ->assertJsonPath('card.has_qr', true)
        ->assertJsonPath('card.can_download_pdf', true)
        ->assertJsonPath('card.valid_until', fn ($value) => filled($value));

    $member->refresh();
    expect($member->qr_token)->not()->toBeNull();
    expect($member->card_valid_until)->not()->toBeNull();

    $this->get('/api/mobile/v1/member/card/qr', $headers)
        ->assertOk()
        ->assertHeader('Content-Type');

    $pdfResponse = $this->get('/api/mobile/v1/member/card/pdf', $headers)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($pdfResponse->headers->get('Content-Disposition'))->toContain('KTA-');

    expect(ActivityLog::where('actor_id', $user->id)->where('action', 'card_pdf_download')->exists())->toBeTrue();
});

test('mobile member card verify endpoint returns safe json only', function () {
    [$user, $member] = mobileUserWithMember();
    $member->forceFill([
        'qr_token' => 'safe-verify-token',
        'card_valid_until' => now()->addYear()->toDateString(),
        'phone' => '081234567890',
        'address' => 'Alamat sensitif',
    ])->save();

    $this->getJson('/api/mobile/v1/member/card/verify/safe-verify-token')
        ->assertOk()
        ->assertJsonPath('card.full_name', $member->full_name)
        ->assertJsonPath('card.unit', $member->unit?->name)
        ->assertJsonMissing(['email' => $member->email])
        ->assertJsonMissing(['phone' => '081234567890'])
        ->assertJsonMissing(['address' => 'Alamat sensitif'])
        ->assertJsonStructure(['card' => ['full_name', 'unit', 'status', 'valid_until'], 'scanned_at']);

    expect(ActivityLog::where('subject_id', $member->id)->where('action', 'card_verification_scan')->exists())->toBeTrue();
});

test('mobile member card requires kta before issuing digital card', function () {
    [$user, $member] = mobileUserWithMember();
    $member->forceFill(['kta_number' => null, 'qr_token' => null, 'card_valid_until' => null])->save();

    $this->getJson('/api/mobile/v1/member/card', ['Authorization' => 'Bearer '.mobileToken($user)])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Nomor KTA belum tersedia.');
});

test('mobile notification actions are limited to the authenticated user', function () {
    [$user] = mobileUserWithMember();
    [$otherUser] = mobileUserWithMember();
    $ownA = Notification::create(['id' => (string) Str::uuid(), 'type' => 'updates', 'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'message' => 'Own A', 'data' => []]);
    $ownB = Notification::create(['id' => (string) Str::uuid(), 'type' => 'updates', 'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'message' => 'Own B', 'data' => []]);
    $other = Notification::create(['id' => (string) Str::uuid(), 'type' => 'updates', 'notifiable_type' => User::class, 'notifiable_id' => $otherUser->id, 'message' => 'Other', 'data' => []]);
    $headers = ['Authorization' => 'Bearer '.mobileToken($user)];

    $this->postJson("/api/mobile/v1/notifications/{$ownA->id}/read", [], $headers)
        ->assertOk()
        ->assertJsonPath('notification.read_at', fn ($value) => filled($value));

    $this->postJson("/api/mobile/v1/notifications/{$ownA->id}/unread", [], $headers)
        ->assertOk()
        ->assertJsonPath('notification.read_at', null);

    $this->postJson('/api/mobile/v1/notifications/read-batch', ['ids' => [$ownA->id, $ownB->id, $other->id]], $headers)
        ->assertOk()
        ->assertJsonPath('count', 2);

    $this->postJson('/api/mobile/v1/notifications/read-all', [], $headers)
        ->assertOk();

    expect(Notification::find($other->id)->read_at)->toBeNull();

    $this->getJson('/api/mobile/v1/notifications/recent', $headers)
        ->assertOk()
        ->assertJsonFragment(['message' => 'Own A'])
        ->assertJsonMissing(['message' => 'Other']);
});

test('mobile aspiration detail support categories and tags work within policy', function () {
    [$user, $member, $unit] = mobileUserWithMember();
    [$author, $authorMember] = mobileUserWithMember($unit);
    $category = AspirationCategory::factory()->create(['name' => 'Fasilitas']);
    $aspiration = Aspiration::factory()->create([
        'member_id' => $authorMember->id,
        'user_id' => $author->id,
        'organization_unit_id' => $unit->id,
        'category_id' => $category->id,
        'title' => 'Aspirasi didukung',
        'support_count' => 0,
    ]);
    $headers = ['Authorization' => 'Bearer '.mobileToken($user)];

    $this->getJson("/api/mobile/v1/aspirations/{$aspiration->id}", $headers)
        ->assertOk()
        ->assertJsonPath('aspiration.title', 'Aspirasi didukung');

    $this->postJson("/api/mobile/v1/aspirations/{$aspiration->id}/support", [], $headers)
        ->assertOk()
        ->assertJsonPath('is_supported', true)
        ->assertJsonPath('support_count', 1);

    expect(AspirationSupport::where('member_id', $member->id)->where('aspiration_id', $aspiration->id)->exists())->toBeTrue();

    $this->deleteJson("/api/mobile/v1/aspirations/{$aspiration->id}/support", [], $headers)
        ->assertOk()
        ->assertJsonPath('is_supported', false)
        ->assertJsonPath('support_count', 0);

    $this->getJson('/api/mobile/v1/aspiration-categories', $headers)
        ->assertOk()
        ->assertJsonFragment(['name' => 'Fasilitas']);

    $this->getJson('/api/mobile/v1/aspiration-tags', $headers)
        ->assertOk();
});

test('mobile announcements dashboard and feedback are scoped to authenticated user', function () {
    [$user, , $unit] = mobileUserWithMember();
    [$otherUser, , $otherUnit] = mobileUserWithMember();
    $own = Announcement::create([
        'title' => 'Pengumuman Unit',
        'body' => 'Isi pengumuman unit',
        'scope_type' => 'unit',
        'organization_unit_id' => $unit->id,
        'is_active' => true,
        'pin_to_dashboard' => true,
        'created_by' => $user->id,
    ]);
    Announcement::create([
        'title' => 'Pengumuman Unit Lain',
        'body' => 'Tidak boleh muncul',
        'scope_type' => 'unit',
        'organization_unit_id' => $otherUnit->id,
        'is_active' => true,
        'pin_to_dashboard' => true,
        'created_by' => $otherUser->id,
    ]);
    $headers = ['Authorization' => 'Bearer '.mobileToken($user)];

    $this->getJson('/api/mobile/v1/announcements', $headers)
        ->assertOk()
        ->assertJsonFragment(['title' => 'Pengumuman Unit'])
        ->assertJsonMissing(['title' => 'Pengumuman Unit Lain']);

    $this->getJson("/api/mobile/v1/announcements/{$own->id}", $headers)
        ->assertOk()
        ->assertJsonPath('announcement.id', $own->id);

    $this->postJson("/api/mobile/v1/announcements/{$own->id}/dismiss", [], $headers)
        ->assertOk()
        ->assertJsonPath('status', 'ok');

    expect(AnnouncementDismissal::where('announcement_id', $own->id)->where('user_id', $user->id)->exists())->toBeTrue();

    $this->getJson('/api/mobile/v1/dashboard', $headers)
        ->assertOk()
        ->assertJsonPath('profile.full_name', $user->fresh()->linkedMember->full_name)
        ->assertJsonMissing(['title' => 'Pengumuman Unit Lain']);

    $this->postJson('/api/mobile/v1/feedback', ['rating' => 5, 'message' => 'Aplikasi mobile siap'], $headers)
        ->assertOk()
        ->assertJsonPath('status', 'ok');

    expect(ActivityLog::where('actor_id', $user->id)->where('action', 'feedback_submitted')->exists())->toBeTrue();
});
