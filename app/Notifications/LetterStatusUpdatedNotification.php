<?php

namespace App\Notifications;

use App\Models\Letter;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LetterStatusUpdatedNotification extends Notification
{
    use Queueable;

    protected array $actionMessages = [
        'approved' => 'Surat Anda telah disetujui',
        'revision' => 'Surat Anda memerlukan revisi',
        'rejected' => 'Surat Anda ditolak',
        'sent' => 'Surat baru diterima',
        'archived' => 'Surat telah diarsipkan',
    ];

    public function __construct(
        public Letter $letter,
        public string $action
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $message = $this->actionMessages[$this->action] ?? "Status surat berubah";

        return [
            'message' => "{$message}: {$this->letter->subject}",
            'category' => 'surat',
            'link' => "/letters/{$this->letter->id}",
            'letter_id' => $this->letter->id,
            'action' => $this->action,
            'urgency' => $this->letter->urgency,
            'confidentiality' => $this->letter->confidentiality,
        ];
    }
}
