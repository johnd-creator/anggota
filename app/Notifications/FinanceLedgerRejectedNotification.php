<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\FinanceLedger;

class FinanceLedgerRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(public FinanceLedger $ledger, public string $reason)
    {
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => 'Transaksi keuangan ditolak: ' . ($this->reason ?: 'Tanpa alasan'),
            'category' => 'finance',
            'link' => '/finance/ledgers',
        ];
    }
}

