<?php

namespace App\Console\Commands;

use App\Models\DuesPayment;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateMonthlyDues extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dues:generate 
        {--period= : Target period in YYYY-MM format (default: current month)}
        {--backfill= : Start period for backfill in YYYY-MM format}
        {--dry-run : Preview without creating records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly dues payment records for all active members';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $targetPeriod = $this->option('period') ?: now()->format('Y-m');
        $backfillStart = $this->option('backfill');
        $dryRun = $this->option('dry-run');

        // Validate period format
        if (!$this->isValidPeriod($targetPeriod)) {
            $this->error("Invalid period format: {$targetPeriod}. Expected YYYY-MM.");
            return 1;
        }

        if ($backfillStart && !$this->isValidPeriod($backfillStart)) {
            $this->error("Invalid backfill period format: {$backfillStart}. Expected YYYY-MM.");
            return 1;
        }

        // Determine periods to process
        $periods = $backfillStart
            ? $this->getPeriodRange($backfillStart, $targetPeriod)
            : [$targetPeriod];

        if (count($periods) > 24) {
            $this->error("Backfill range exceeds 24 months. Use smaller range or run multiple times.");
            return 1;
        }

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($periods as $period) {
            [$created, $skipped] = $this->generateForPeriod($period, $dryRun);
            $totalCreated += $created;
            $totalSkipped += $skipped;

            if ($dryRun) {
                $this->line("[DRY-RUN] Period {$period}: would create {$created}, skip {$skipped}");
            } else {
                $this->info("Period {$period}: created {$created}, skipped {$skipped}");
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->warn("[DRY-RUN] Total: would create {$totalCreated} records, skip {$totalSkipped} existing.");
        } else {
            $this->info("Total: created {$totalCreated} records, skipped {$totalSkipped} existing.");
        }

        return 0;
    }

    /**
     * Generate dues for a single period.
     *
     * @return array{int, int} [created, skipped]
     */
    protected function generateForPeriod(string $period, bool $dryRun): array
    {
        $amount = config('dues.default_amount', 30000);
        $periodEnd = Carbon::createFromFormat('Y-m', $period)->endOfMonth();

        // Get active members with join dates
        $members = Member::where('status', 'aktif')
            ->select('id', 'organization_unit_id', 'join_date', 'created_at')
            ->get();

        if ($members->isEmpty()) {
            return [0, 0];
        }

        // Get existing records for this period
        $existingMemberIds = DuesPayment::where('period', $period)
            ->pluck('member_id')
            ->toArray();

        $skippedExisting = count($existingMemberIds);
        $skippedIneligible = 0;

        // Filter members who need records AND joined before/during this period
        $membersToCreate = $members->filter(function ($m) use ($existingMemberIds, $periodEnd, &$skippedIneligible) {
            // Skip if already exists
            if (in_array($m->id, $existingMemberIds)) {
                return false;
            }

            // Check join date validity (must have joined by end of period)
            // Use created_at as fallback if join_date is missing
            $joinDate = $m->join_date ? Carbon::parse($m->join_date) : $m->created_at;

            // If even created_at is null (unlikely but safe), assume eligible
            if (!$joinDate) {
                return true;
            }

            if ($joinDate->lte($periodEnd)) {
                return true;
            }

            $skippedIneligible++;
            return false;
        });

        $skipped = $skippedExisting + $skippedIneligible;

        $toCreate = $membersToCreate->count();

        if ($dryRun || $toCreate === 0) {
            return [$toCreate, $skipped];
        }

        // Prepare records for batch insert
        $now = now();
        $records = $membersToCreate->map(fn($member) => [
            'member_id' => $member->id,
            'organization_unit_id' => $member->organization_unit_id,
            'period' => $period,
            'status' => 'unpaid',
            'amount' => $amount,
            'paid_at' => null,
            'notes' => null,
            'recorded_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->toArray();

        // Insert in chunks to avoid memory issues
        foreach (array_chunk($records, 500) as $chunk) {
            DB::table('dues_payments')->insertOrIgnore($chunk);
        }

        // Audit log (system action)
        app(\App\Services\AuditService::class)->log(
            'dues.generate',
            [
                'period' => $period,
                'created_count' => $toCreate,
                'skipped_count' => $skipped,
                'skipped_existing' => $skippedExisting,
                'skipped_ineligible' => $skippedIneligible,
                'dry_run' => false,
            ],
            null, // No subject
            null, // System user (null)
            null  // No specific unit
        );

        return [$toCreate, $skipped];
    }

    /**
     * Validate period format (YYYY-MM).
     */
    protected function isValidPeriod(string $period): bool
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) === 1;
    }

    /**
     * Get array of periods from start to end (inclusive).
     *
     * @return string[]
     */
    protected function getPeriodRange(string $start, string $end): array
    {
        $startDate = Carbon::createFromFormat('Y-m', $start)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $end)->startOfMonth();

        if ($startDate->gt($endDate)) {
            return [$end];
        }

        $periods = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $periods[] = $current->format('Y-m');
            $current->addMonth();
        }

        return $periods;
    }
}
