<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MutationApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $payload = []) {}

    public function build()
    {
        $subject = 'Mutasi Anggota Disetujui';
        return $this->subject($subject)
            ->view('emails.general_notification')
            ->with([
                'category' => 'mutations',
                'message' => 'Pengajuan mutasi telah disetujui dan dieksekusi.',
                'data' => $this->payload,
            ]);
    }
}

