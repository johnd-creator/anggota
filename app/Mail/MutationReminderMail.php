<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MutationReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $payload = []) {}

    public function build()
    {
        $subject = 'Pengingat SLA Mutasi';
        return $this->subject($subject)
            ->view('emails.general_notification')
            ->with([
                'category' => 'mutations',
                'message' => 'Terdapat pengajuan mutasi yang mendekati atau melewati SLA.',
                'data' => $this->payload,
            ]);
    }
}

