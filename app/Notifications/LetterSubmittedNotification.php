<?php

namespace App\Notifications;

use App\Models\Letter;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LetterSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Letter $letter
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'message' => "Surat baru menunggu persetujuan: {$this->letter->subject}",
            'category' => 'surat',
            'link' => "/letters/{$this->letter->id}",
            'letter_id' => $this->letter->id,
            'urgency' => $this->letter->urgency,
            'confidentiality' => $this->letter->confidentiality,
        ];
    }
}
