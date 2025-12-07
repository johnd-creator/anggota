<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SessionTerminatedNotification extends Notification
{
    use Queueable;

    public function __construct(public string $reason = 'Administrator terminating session')
    {
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => 'Sesi Anda telah dihentikan oleh admin. Alasan: ' . $this->reason,
            'cta_url' => '/login',
            'cta_label' => 'Login Ulang',
            'category' => 'security',
        ];
    }
}
