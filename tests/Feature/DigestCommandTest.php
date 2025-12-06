<?php

use App\Models\User;
use App\Models\NotificationPreference;
use App\Models\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Artisan;
use App\Mail\GeneralNotificationMail;
use Illuminate\Support\Str;

test('digest command queues summary email for users with digest', function () {
    Mail::fake();
    Artisan::call('migrate', ['--force' => true]);
    $user = User::factory()->create(['email' => 'digest@example.com']);
    NotificationPreference::updateOrCreate(['user_id' => $user->id], [
        'channels' => [
            'mutations' => ['email' => true, 'inapp' => true, 'wa' => false],
            'updates' => ['email' => true, 'inapp' => true, 'wa' => false],
            'onboarding' => ['email' => true, 'inapp' => true, 'wa' => false],
        ],
        'digest_daily' => true,
    ]);

    $y = now()->subDay();
    Notification::create(['id' => (string) Str::uuid(), 'type' => 'mutations', 'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'message' => 'Mutasi X', 'data' => [], 'created_at' => $y]);
    Notification::create(['id' => (string) Str::uuid(), 'type' => 'updates', 'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'message' => 'Update Y', 'data' => [], 'created_at' => $y]);

    Artisan::call('notifications:digest');

    Mail::assertQueued(GeneralNotificationMail::class, function($m){
        return $m->category === 'digest';
    });
});
