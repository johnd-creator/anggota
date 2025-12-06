<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Notification;
use App\Models\NotificationPreference;
use Illuminate\Support\Facades\Mail;
use App\Mail\GeneralNotificationMail;

class NotificationDigestCommand extends Command
{
    protected $signature = 'notifications:digest';
    protected $description = 'Kirim ringkasan harian notifikasi untuk pengguna yang mengaktifkan digest';

    public function handle(): int
    {
        $date = now()->subDay()->toDateString();
        $users = User::whereHas('notificationPreference', fn($q)=>$q->where('digest_daily', true))->get();
        foreach ($users as $user) {
            $channels = NotificationPreference::where('user_id', $user->id)->value('channels') ?? [];
            $enabledCats = array_keys(array_filter($channels, fn($c)=>($c['email'] ?? false) || ($c['inapp'] ?? false)));
            if (empty($enabledCats)) continue;

            $counts = [];
            foreach (['mutations','onboarding','updates'] as $cat) {
                $counts[$cat] = Notification::where('notifiable_type', User::class)
                    ->where('notifiable_id', $user->id)
                    ->where('type', $cat)
                    ->whereDate('created_at', $date)
                    ->count();
            }

            $summary = 'Ringkasan notifikasi tanggal ' . $date . ': ' . implode(', ', array_map(function($k) use ($counts){ return ucfirst($k) . ' ' . ($counts[$k] ?? 0); }, array_keys($counts)));

            Mail::to($user->email)->queue(new GeneralNotificationMail('digest', $summary, ['date' => $date, 'counts' => $counts]));
        }
        $this->info('Digest harian dikirim');
        return self::SUCCESS;
    }
}

