<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Member;
use App\Models\Role;

class LinkUsersMembers extends Command
{
    protected $signature = 'link:users-members {--dry-run}';
    protected $description = 'Cocokkan user dan member berdasarkan email, isi users.member_id serta members.user_id jika kosong';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $linked = 0; $skipped = 0;
        $users = User::all();
        foreach ($users as $u) {
            if ($u->member_id) { $skipped++; continue; }
            $m = Member::where('email', $u->email)->first();
            if ($m) {
                $this->info("Linking {$u->email} -> member #{$m->id}");
                if (!$dry) {
                    $u->member_id = $m->id;
                    if (!$m->user_id) { $m->user_id = $u->id; $m->save(); }
                    if (!$u->role || $u->role->name === 'reguler') {
                        $role = Role::where('name','anggota')->first();
                        if ($role) $u->role_id = $role->id;
                    }
                    $u->save();
                }
                $linked++;
            } else {
                $skipped++;
            }
        }
        $this->info("Linked: {$linked}, Skipped: {$skipped}");
        return self::SUCCESS;
    }
}

