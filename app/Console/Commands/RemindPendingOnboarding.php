<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PendingMember;
use App\Models\User;
use App\Services\NotificationService;

class RemindPendingOnboarding extends Command
{
    protected $signature = 'sla:remind-onboarding';
    protected $description = 'Kirim pengingat SLA untuk onboarding pending';

    public function handle(): int
    {
        $pending = PendingMember::where('status','pending')->where('created_at','<', now()->subDays(3))->count();
        if ($pending) {
            $admins = User::whereHas('role', fn($q)=>$q->whereIn('name',['super_admin','admin_unit']))->get();
            foreach ($admins as $a) {
                NotificationService::send($a, 'onboarding', 'Onboarding pending >3 hari: '.$pending, ['event' => 'sla_reminder', 'count' => $pending]);
            }
        }
        $this->info('Pengingat SLA onboarding dikirim');
        return self::SUCCESS;
    }
}

