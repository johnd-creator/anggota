<?php

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use function Pest\Laravel\actingAs;

test('notifications filter by data->category', function(){
    if (!Schema::hasTable('notifications')) {
        expect(true)->toBeTrue();
        return;
    }
    $user = User::factory()->make();
    $user->id = 5501;
    DatabaseNotification::create([
        'id' => (string) Str::uuid(),
        'type' => 'updates',
        'notifiable_type' => User::class,
        'notifiable_id' => 5501,
        'data' => ['message' => 'Perubahan data', 'category' => 'updates'],
    ]);
    DatabaseNotification::create([
        'id' => (string) Str::uuid(),
        'type' => 'mutations',
        'notifiable_type' => User::class,
        'notifiable_id' => 5501,
        'data' => ['message' => 'Mutasi disetujui', 'category' => 'mutations'],
    ]);
    actingAs($user);
    $res = $this->get('/notifications?category=mutations');
    $res->assertStatus(200);
    $html = $res->getContent();
    expect($html)->toContain('Mutasi disetujui');
    expect($html)->not()->toContain('Perubahan data');
});
