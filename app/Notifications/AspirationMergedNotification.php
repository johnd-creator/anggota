<?php

namespace App\Notifications;

use App\Models\Aspiration;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AspirationMergedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Aspiration $aspiration,
        protected array $data
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'aspiration_merged',
            'aspiration_id' => $this->aspiration->id,
            'title' => $this->aspiration->title,
            'target_id' => $this->data['target_id'],
            'target_title' => $this->data['target_title'],
            'message' => "Aspirasi \"{$this->aspiration->title}\" telah digabungkan ke: \"{$this->data['target_title']}\"",
        ];
    }
}
