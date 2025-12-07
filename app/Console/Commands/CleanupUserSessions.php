<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupUserSessions extends Command
{
    protected $signature = 'sessions:cleanup';
    protected $description = 'Clean up expired user sessions tracking records';

    public function handle()
    {
        $ttlDays = config('session.track_ttl', env('SESSION_TRACK_TTL', 14));
        $date = now()->subDays($ttlDays);

        $count = DB::table('user_sessions')
            ->where('last_activity', '<', $date)
            ->delete();

        $this->info("Cleaned up {$count} expired user session records.");
    }
}
