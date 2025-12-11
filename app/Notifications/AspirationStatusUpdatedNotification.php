<?php

namespace App\Notifications;

use App\Models\Aspiration;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AspirationStatusUpdatedNotification extends Notification
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
        $statusLabels = [
            'new' => 'Baru',
            'in_progress' => 'Sedang Diproses',
            'resolved' => 'Selesai',
        ];

        return [
            'type' => 'aspiration_status_updated',
            'aspiration_id' => $this->aspiration->id,
            'title' => $this->aspiration->title,
            'old_status' => $this->data['old_status'] ?? null,
            'new_status' => $this->data['new_status'],
            'old_status_label' => $statusLabels[$this->data['old_status']] ?? $this->data['old_status'],
            'new_status_label' => $statusLabels[$this->data['new_status']] ?? $this->data['new_status'],
            'message' => "Aspirasi \"{$this->aspiration->title}\" telah diperbarui ke status: " . ($statusLabels[$this->data['new_status']] ?? $this->data['new_status']),
            'link' => "/admin/aspirations/{$this->aspiration->id}",
            'category' => 'aspiration',
        ];
    }
}
