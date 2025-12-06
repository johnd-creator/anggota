<?php

use App\Models\User;
use App\Models\MutationRequest;
use App\Models\NotificationPreference;
use App\Models\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use App\Models\OrganizationUnit;
use App\Models\Member;

test('sla remind mutations marks breach and sends in-app', function () {
    Mail::fake();
    Artisan::call('migrate', ['--force' => true]);
    $admin = User::factory()->create(['email' => 'admin@example.com']);

    NotificationPreference::updateOrCreate(['user_id' => $admin->id], [
        'channels' => ['mutations' => ['email' => false, 'inapp' => true, 'wa' => false]],
        'digest_daily' => false,
    ]);

    $from = OrganizationUnit::factory()->create();
    $to = OrganizationUnit::factory()->create();
    $member = Member::create([
        'full_name' => 'Member X',
        'email' => 'memberx@example.com',
        'employment_type' => 'organik',
        'status' => 'aktif',
        'join_date' => now()->subYear()->toDateString(),
        'organization_unit_id' => $from->id,
        'nra' => 'TEST-123',
        'join_year' => (int) now()->year,
        'sequence_number' => 1,
    ]);

    $m = MutationRequest::create([
        'member_id' => $member->id,
        'from_unit_id' => $from->id,
        'to_unit_id' => $to->id,
        'effective_date' => now()->toDateString(),
        'reason' => 'Test',
        'status' => 'pending',
        'submitted_by' => $admin->id,
        'approved_by' => null,
    ]);
    $m->created_at = now()->subDays(6);
    $m->save();

    $cnt = MutationRequest::where('status','pending')
        ->whereDate('created_at','<=', now()->subDays(5)->toDateString())
        ->count();
    expect($cnt)->toBe(1);

    $cmd = app(\App\Console\Commands\RemindPendingMutations::class);
    $style = new \Illuminate\Console\OutputStyle(new \Symfony\Component\Console\Input\ArrayInput([]), new \Symfony\Component\Console\Output\NullOutput());
    $cmd->setOutput($style);
    $cmd->handle();

    $m->refresh();
    expect($m->sla_status)->toBe('breach');
    $inapp = Notification::where('notifiable_type', User::class)->where('notifiable_id', $admin->id)->where('type','mutations')->exists();
    expect($inapp)->toBeTrue();
});
