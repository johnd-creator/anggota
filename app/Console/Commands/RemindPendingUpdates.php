<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MemberUpdateRequest;
use App\Models\User;
use App\Services\NotificationService;

class RemindPendingUpdates extends Command
{
    protected $signature = 'sla:remind-updates';
    protected $description = 'Kirim pengingat SLA untuk permintaan update data pending';

    public function handle(): int
    {
        $pending = MemberUpdateRequest::where('status','pending')->where('created_at','<', now()->subDays(3))->count();
        if ($pending) {
            $admins = User::whereHas('role', fn($q)=>$q->whereIn('name',['super_admin','admin_unit']))->get();
            foreach ($admins as $a) {
                NotificationService::send($a, 'updates', 'Update data pending >3 hari: '.$pending, ['event' => 'sla_reminder', 'count' => $pending]);
            }
        }
        $this->info('Pengingat SLA update data dikirim');
        return self::SUCCESS;
    }
}

