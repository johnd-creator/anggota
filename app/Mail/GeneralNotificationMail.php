<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GeneralNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $category, public string $message, public array $data = []) {}

    public function build()
    {
        return $this->subject('SIM-SP Notification: ' . ucfirst($this->category))
            ->view('emails.general_notification')
            ->with(['category' => $this->category, 'message' => $this->message, 'data' => $this->data]);
    }
}

