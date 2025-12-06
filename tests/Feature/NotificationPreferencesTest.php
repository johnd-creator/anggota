<?php

use App\Models\User;
use App\Models\NotificationPreference;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;
use App\Mail\GeneralNotificationMail;
use Illuminate\Support\Facades\Artisan;
use function Pest\Laravel\withoutMiddleware;
use function Pest\Laravel\patch;

test('update preferences per kategori and skip email when disabled', function () {
    Mail::fake();
    Artisan::call('migrate', ['--force' => true]);
    withoutMiddleware();
    $user = User::factory()->create();
    \Illuminate\Support\Facades\Auth::login($user);

    $payload = [
        'channels' => [
            'mutations' => ['email' => false, 'inapp' => true, 'wa' => false],
            'updates' => ['email' => true, 'inapp' => true, 'wa' => false],
            'onboarding' => ['email' => true, 'inapp' => true, 'wa' => false],
            'security' => ['email' => true, 'inapp' => true, 'wa' => false],
        ],
        'digest_daily' => false,
    ];
    $resp = patch('/settings/notifications', $payload);
    $resp->assertStatus(200);
    $pref = NotificationPreference::where('user_id', $user->id)->first();
    expect($pref)->not->toBeNull();
    expect($pref->channels['mutations']['email'])->toBeFalse();

    NotificationService::send($user, 'mutations', 'Tes', []);
    Mail::assertNothingQueued();

    NotificationService::send($user, 'updates', 'Tes', []);
    Mail::assertQueued(GeneralNotificationMail::class);
});
