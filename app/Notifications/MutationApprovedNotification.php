<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\MutationRequest;

class MutationApprovedNotification extends Notification
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
            'message' => 'Mutasi disetujui: ' . optional($this->mutation->member)->full_name,
            'cta_url' => '/member/portal?tab=riwayat',
            'cta_label' => 'Lihat Riwayat',
            'category' => 'mutations',
        ];
    }
}

