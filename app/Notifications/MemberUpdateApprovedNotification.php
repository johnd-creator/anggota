<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\MemberUpdateRequest;

class MemberUpdateApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(public MemberUpdateRequest $requestModel)
    {
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => 'Perubahan data anggota disetujui',
            'link' => '/member/profile',
            'category' => 'updates',
        ];
    }
}
