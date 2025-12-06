<?php

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
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
