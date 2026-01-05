<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\Notification;
use App\Models\ActivityLog;
use App\Jobs\SendWebhookNotification;
use App\Mail\GeneralNotificationMail;
use App\Mail\MutationApprovedMail;
use App\Mail\MutationReminderMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public static function send($user, string $category, string $message, array $data = []): void
    {
        $prefs = NotificationPreference::where('user_id', $user->id)->first();
        $catPrefs = $prefs?->channels[$category] ?? null;

        // Use role-aware defaults if no preference set
        $channels = $catPrefs ?? self::getDefaultChannels($user, $category);

        $digestDaily = (bool) ($prefs?->digest_daily ?? false);

        if (($channels['inapp'] ?? false) === true) {
            Notification::create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => $category,
                'notifiable_type' => \App\Models\User::class,
                'notifiable_id' => $user->id,
                'message' => $message,
                'data' => $data,
            ]);
            ActivityLog::create(['actor_id' => $user->id, 'action' => 'notify_inapp', 'subject_type' => \App\Models\User::class, 'subject_id' => $user->id, 'payload' => ['category' => $category, 'message' => $message]]);
        }

        if (($channels['email'] ?? false) === true && !$digestDaily) {
            try {
                $mailable = null;
                if ($category === 'mutations' && ($data['event'] ?? '') === 'approved') {
                    $mailable = new MutationApprovedMail($data);
                } elseif ($category === 'mutations' && ($data['event'] ?? '') === 'sla_reminder') {
                    $mailable = new MutationReminderMail($data);
                } else {
                    $mailable = new GeneralNotificationMail($category, $message, $data);
                }
                Mail::to($user->email)->queue($mailable);
            } catch (\Throwable $e) {
                Log::warning('mail_queue_failed', ['error' => $e->getMessage()]);
            }
            ActivityLog::create(['actor_id' => $user->id, 'action' => 'notify_email', 'subject_type' => \App\Models\User::class, 'subject_id' => $user->id, 'payload' => ['category' => $category]]);
        }

        if (($channels['wa'] ?? false) === true) {
            dispatch(new SendWebhookNotification($user->id, $category, $message, $data));
            ActivityLog::create(['actor_id' => $user->id, 'action' => 'notify_webhook', 'subject_type' => \App\Models\User::class, 'subject_id' => $user->id, 'payload' => ['category' => $category]]);
        }
    }

    private static function getDefaultChannels($user, string $category): array
    {
        $base = ['inapp' => true, 'email' => false, 'wa' => false];

        // Bendahara defaults
        if ($user->hasRole('bendahara')) {
            if (in_array($category, ['dues', 'finance'])) {
                return ['inapp' => true, 'email' => true, 'wa' => false];
            }
        }

        // Admin Unit defaults
        if ($user->hasRole('admin_unit')) {
            if (in_array($category, ['onboarding', 'updates', 'aspirations'])) {
                return ['inapp' => true, 'email' => true, 'wa' => false];
            }
        }

        return $base;
    }
}
