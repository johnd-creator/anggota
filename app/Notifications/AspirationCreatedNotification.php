<?php

namespace App\Notifications;

use App\Models\Aspiration;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AspirationCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Aspiration $aspiration
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'aspiration_created',
            'aspiration_id' => $this->aspiration->id,
            'title' => $this->aspiration->title,
            'message' => "Aspirasi baru: \"{$this->aspiration->title}\"",
            'link' => "/admin/aspirations/{$this->aspiration->id}",
            'category' => 'aspiration',
        ];
    }
}
