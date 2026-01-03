<?php

namespace App\Console\Commands;

use App\Models\Letter;
use Illuminate\Console\Command;

class LettersSlaMarkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'letters:sla-mark';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark letters as SLA breached if past due date';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = Letter::where('status', 'submitted')
            ->where('sla_status', 'ok')
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->each(function (Letter $letter) {
                $letter->markSlaBreach();
            });

        $updated = Letter::where('status', 'submitted')
            ->where('sla_status', 'breach')
            ->whereNotNull('sla_marked_at')
            ->count();

        $this->info("Marked {$updated} letters as SLA breached.");

        return self::SUCCESS;
    }
}
