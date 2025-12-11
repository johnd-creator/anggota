<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\MemberUpdateRequest;

class MemberUpdateRejectedNotification extends Notification
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
            'message' => 'Perubahan data anggota ditolak: ' . ($this->requestModel->notes ?? ''),
            'link' => '/member/profile',
            'category' => 'updates',
        ];
    }
}
