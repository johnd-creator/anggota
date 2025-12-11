<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Member;

class OnboardingApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(public Member $member)
    {
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => 'Onboarding disetujui untuk ' . ($this->member->full_name ?? 'akun Anda'),
            'link' => '/member/portal',
            'category' => 'onboarding',
        ];
    }
}
