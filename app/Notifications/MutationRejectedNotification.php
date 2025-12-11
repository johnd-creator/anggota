<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\MutationRequest;

class MutationRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(public MutationRequest $mutation)
    {
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => 'Mutasi ditolak: ' . optional($this->mutation->member)->full_name,
            'link' => '/member/portal?tab=riwayat',
            'category' => 'mutations',
        ];
    }
}

