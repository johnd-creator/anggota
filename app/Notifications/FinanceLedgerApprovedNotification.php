<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\FinanceLedger;

class FinanceLedgerApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(public FinanceLedger $ledger)
    {
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => 'Transaksi keuangan disetujui: ' . ($this->ledger->description ?? 'Tanpa deskripsi'),
            'category' => 'finance',
            'link' => '/finance/ledgers',
        ];
    }
}

