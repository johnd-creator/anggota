<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\PendingMember;

class OnboardingRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(public PendingMember $pending)
    {
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => 'Onboarding ditolak: ' . ($this->pending->notes ?? 'periksa detail'),
            'cta_url' => '/help',
            'cta_label' => 'Bantuan',
            'category' => 'onboarding',
        ];
    }
}

