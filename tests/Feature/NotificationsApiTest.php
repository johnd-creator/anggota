<?php

use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use function Pest\Laravel\withoutMiddleware;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

test('notifications list supports category and search filters', function () {
    Artisan::call('migrate', ['--force' => true]);
    withoutMiddleware();
    $user = User::factory()->create();
    Auth::login($user);

    Notification::create(['id' => (string) Str::uuid(), 'type' => 'mutations', 'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'message' => 'Mutasi anggota A ke unit B', 'data' => []]);
    Notification::create(['id' => (string) Str::uuid(), 'type' => 'updates', 'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'message' => 'Perubahan data alamat anggota', 'data' => []]);

    $resp = get('/notifications?category=mutations&search=anggota');
    $resp->assertStatus(200);
});

test('notifications read endpoints mark items as read', function () {
    Artisan::call('migrate', ['--force' => true]);
    withoutMiddleware();
    $user = User::factory()->create();
    Auth::login($user);

    $n = Notification::create(['id' => (string) Str::uuid(), 'type' => 'mutations', 'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'message' => 'Tes baca', 'data' => []]);
    post('/notifications/'.$n->id.'/read')->assertStatus(302);
    expect(Notification::find($n->id)->read_at)->not->toBeNull();

    $n2 = Notification::create(['id' => (string) Str::uuid(), 'type' => 'mutations', 'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'message' => 'Tes bulk', 'data' => []]);
    post('/notifications/read-all')->assertStatus(302);
    expect(Notification::find($n2->id)->read_at)->not->toBeNull();
});

test('notifications recent endpoint does not change read state', function () {
    Artisan::call('migrate', ['--force' => true]);

    if (! Schema::hasTable('notifications')) {
        $this->markTestSkipped('notifications table is unavailable');
    }

    $role = Role::firstOrCreate(['name' => 'anggota'], ['label' => 'Anggota']);
    $user = User::factory()->create(['role_id' => $role->id]);
    $notification = DatabaseNotification::create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\AspirationCreatedNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => ['message' => 'Aspirasi baru'],
    ]);

    $this->actingAs($user)
        ->get('/notifications/recent')
        ->assertOk()
        ->assertJsonFragment(['id' => $notification->id]);

    expect($notification->fresh()->read_at)->toBeNull();
});

test('notifications mark read only affects authenticated user item', function () {
    Artisan::call('migrate', ['--force' => true]);

    if (! Schema::hasTable('notifications')) {
        $this->markTestSkipped('notifications table is unavailable');
    }

    $role = Role::firstOrCreate(['name' => 'anggota'], ['label' => 'Anggota']);
    $user = User::factory()->create(['role_id' => $role->id]);
    $otherUser = User::factory()->create(['role_id' => $role->id]);

    $own = DatabaseNotification::create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\AspirationCreatedNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => ['message' => 'Milik saya'],
    ]);

    $others = DatabaseNotification::create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\AspirationCreatedNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $otherUser->id,
        'data' => ['message' => 'Milik user lain'],
    ]);

    $this->actingAs($user)
        ->post('/notifications/'.$others->id.'/read')
        ->assertStatus(302);

    expect($others->fresh()->read_at)->toBeNull();

    $this->actingAs($user)
        ->post('/notifications/'.$own->id.'/read')
        ->assertStatus(302);

    expect($own->fresh()->read_at)->not->toBeNull();
});

test('notifications mark all read only changes authenticated user unread items', function () {
    Artisan::call('migrate', ['--force' => true]);

    if (! Schema::hasTable('notifications')) {
        $this->markTestSkipped('notifications table is unavailable');
    }

    $role = Role::firstOrCreate(['name' => 'anggota'], ['label' => 'Anggota']);
    $user = User::factory()->create(['role_id' => $role->id]);
    $otherUser = User::factory()->create(['role_id' => $role->id]);

    $ownUnreadA = DatabaseNotification::create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\AspirationCreatedNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => ['message' => 'Unread A'],
    ]);
    $ownUnreadB = DatabaseNotification::create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\AspirationCreatedNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => ['message' => 'Unread B'],
    ]);
    $otherUnread = DatabaseNotification::create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\AspirationCreatedNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $otherUser->id,
        'data' => ['message' => 'Unread other'],
    ]);

    $this->actingAs($user)
        ->post('/notifications/read-all')
        ->assertStatus(302);

    expect($ownUnreadA->fresh()->read_at)->not->toBeNull();
    expect($ownUnreadB->fresh()->read_at)->not->toBeNull();
    expect($otherUnread->fresh()->read_at)->toBeNull();
});
