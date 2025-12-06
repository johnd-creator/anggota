<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MutationRequest;
use App\Models\User;
use App\Services\NotificationService;

class RemindPendingMutations extends Command
{
    protected $signature = 'sla:remind-mutations';
    protected $description = 'Kirim pengingat SLA untuk mutasi pending dan tandai status SLA';

    public function handle(): int
    {
        $warning = MutationRequest::where('status','pending')
            ->whereDate('created_at','<=', now()->subDays(3)->toDateString())
            ->get();
        $breach = MutationRequest::where('status','pending')
            ->whereDate('created_at','<=', now()->subDays(5)->toDateString())
            ->get();

        if ($warning->count()) {
            $admins = User::whereHas('role', fn($q)=>$q->whereIn('name',['super_admin','admin_unit']))->get();
            foreach ($admins as $a) {
                NotificationService::send($a, 'mutations', 'SLA mutasi terlewati untuk '.$warning->count().' pengajuan', ['event' => 'sla_reminder', 'count' => $warning->count()]);
            }
        }

        if ($breach->count()) {
            $superAdmins = User::whereHas('role', fn($q)=>$q->where('name','super_admin'))->get();
            foreach ($superAdmins as $a) {
                NotificationService::send($a, 'mutations', 'Eskalasi: mutasi pending >5 hari: '.$breach->count(), ['event' => 'sla_reminder', 'severity' => 'breach', 'count' => $breach->count()]);
            }
            foreach ($breach as $m) {
                if ($m->submitted_by) {
                    $submitter = User::find($m->submitted_by);
                    if ($submitter) NotificationService::send($submitter, 'mutations', 'Pengajuan Anda melewati SLA', ['event' => 'sla_reminder', 'severity' => 'breach', 'id' => $m->id]);
                }
            }
        }

        foreach ($breach as $m) {
            $m->sla_status = 'breach';
            $m->sla_marked_at = now();
            $m->save();
        }
        $warningList = MutationRequest::where('status','pending')
            ->whereDate('created_at','>=', now()->subDays(5)->toDateString())
            ->whereDate('created_at','<=', now()->subDays(3)->toDateString())
            ->get();
        foreach ($warningList as $m) {
            $m->sla_status = 'warning';
            $m->sla_marked_at = now();
            $m->save();
        }

        foreach ($warning as $m) {
            \App\Models\ActivityLog::create(['actor_id' => null, 'action' => 'sla_reminder_sent', 'subject_type' => MutationRequest::class, 'subject_id' => $m->id, 'payload' => ['severity' => 'warning']]);
        }
        foreach ($breach as $m) {
            \App\Models\ActivityLog::create(['actor_id' => null, 'action' => 'sla_reminder_sent', 'subject_type' => MutationRequest::class, 'subject_id' => $m->id, 'payload' => ['severity' => 'breach']]);
        }

        $this->info('Pengingat SLA mutasi dikirim');
        return self::SUCCESS;
    }
}
