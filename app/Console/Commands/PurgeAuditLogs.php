<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PurgeAuditLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:purge
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge old audit logs based on retention policy in config/audit.php';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $retentionConfig = config('audit.retention', []);
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('DRY RUN MODE - No records will be deleted');
            $this->newLine();
        }

        $totalDeleted = 0;
        $deletionPlan = [];

        // Get specific categories (excluding default)
        $specificCategories = array_filter(
            array_keys($retentionConfig),
            fn($c) => $c !== 'default'
        );

        // Build deletion plan per category
        foreach ($retentionConfig as $category => $days) {
            if ($category === 'default') {
                continue; // Handle default after specific categories
            }

            $cutoff = Carbon::now()->subDays($days);
            $count = AuditLog::where('event_category', $category)
                ->where('created_at', '<', $cutoff)
                ->count();

            if ($count > 0) {
                $deletionPlan[$category] = [
                    'count' => $count,
                    'days' => $days,
                ];
            }
        }

        // Handle default category (logs not matching any specific category)
        $defaultDays = $retentionConfig['default'] ?? 365;
        $defaultCutoff = Carbon::now()->subDays($defaultDays);

        $defaultCount = AuditLog::where('created_at', '<', $defaultCutoff)
            ->where(function ($q) use ($specificCategories) {
                $q->whereNull('event_category')
                    ->orWhereNotIn('event_category', $specificCategories);
            })
            ->count();

        if ($defaultCount > 0) {
            $deletionPlan['(other/unset)'] = [
                'count' => $defaultCount,
                'days' => $defaultDays,
            ];
        }

        if (empty($deletionPlan)) {
            $this->info('No audit logs to purge.');
            return self::SUCCESS;
        }

        // Display plan
        $this->table(
            ['Category', 'Records', 'Retention (days)', 'Cutoff Date'],
            collect($deletionPlan)->map(fn($p, $cat) => [
                $cat,
                $p['count'],
                $p['days'],
                Carbon::now()->subDays($p['days'])->toDateTimeString(),
            ])->toArray()
        );

        $totalPlanned = collect($deletionPlan)->sum('count');
        $this->newLine();
        $this->info("Total records to purge: {$totalPlanned}");

        if ($isDryRun) {
            $this->warn('Dry run complete. No records were deleted.');
            return self::SUCCESS;
        }

        // Confirmation
        if (!$this->option('force')) {
            if (!$this->confirm('Do you want to proceed with deletion?', false)) {
                $this->info('Purge cancelled.');
                return self::SUCCESS;
            }
        }

        // Execute deletion
        foreach ($deletionPlan as $category => $plan) {
            $cutoff = Carbon::now()->subDays($plan['days']);

            if ($category === '(other/unset)') {
                // Delete uncategorized/unknown logs
                $deleted = AuditLog::where('created_at', '<', $cutoff)
                    ->where(function ($q) use ($specificCategories) {
                        $q->whereNull('event_category')
                            ->orWhereNotIn('event_category', $specificCategories);
                    })
                    ->delete();
            } else {
                // Delete category-specific logs
                $deleted = AuditLog::where('event_category', $category)
                    ->where('created_at', '<', $cutoff)
                    ->delete();
            }
            $totalDeleted += $deleted;
            $this->line("  Deleted {$deleted} records from category: {$category}");
        }

        $this->newLine();
        $this->info("Purge complete. Total deleted: {$totalDeleted}");

        return self::SUCCESS;
    }
}
