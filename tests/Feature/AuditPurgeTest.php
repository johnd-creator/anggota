<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuditPurgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * Test dry-run mode shows counts but does not delete records.
     */
    public function test_purge_dry_run_does_not_delete_records(): void
    {
        // Create old auth_failed logs (retention: 90 days)
        $this->createOldAuditLogs('auth_failed', 100, 3);

        // Create old member logs (retention: 365 days)
        $this->createOldAuditLogs('member', 400, 2);

        $initialCount = AuditLog::count();
        $this->assertEquals(5, $initialCount);

        // Run dry-run
        $this->artisan('audit:purge', ['--dry-run' => true])
            ->assertSuccessful();

        // Verify no records were deleted
        $this->assertEquals($initialCount, AuditLog::count());
    }

    /**
     * Test force mode deletes records based on retention policy.
     */
    public function test_purge_force_deletes_old_records(): void
    {
        // Create old auth_failed logs (retention: 90 days) - should be deleted
        $this->createOldAuditLogs('auth_failed', 100, 3);

        // Create old member logs (retention: 365 days) - should be deleted
        $this->createOldAuditLogs('member', 400, 2);

        // Create recent auth_failed logs (within retention) - should NOT be deleted
        $this->createOldAuditLogs('auth_failed', 30, 2);

        // Create recent member logs (within retention) - should NOT be deleted
        $this->createOldAuditLogs('member', 100, 1);

        $initialCount = AuditLog::count();
        $this->assertEquals(8, $initialCount);

        // Run force purge
        $this->artisan('audit:purge', ['--force' => true])
            ->assertSuccessful();

        // Verify only old records were deleted
        $remainingCount = AuditLog::count();
        $this->assertEquals(3, $remainingCount); // 2 recent auth_failed + 1 recent member

        // Verify the right records remain
        $this->assertEquals(2, AuditLog::where('event_category', 'auth_failed')->count());
        $this->assertEquals(1, AuditLog::where('event_category', 'member')->count());
    }

    /**
     * Test purge with no old records shows appropriate message.
     */
    public function test_purge_with_no_old_records(): void
    {
        // Create only recent logs
        $this->createOldAuditLogs('auth_failed', 30, 2);

        $this->artisan('audit:purge', ['--force' => true])
            ->assertSuccessful();

        $this->assertEquals(2, AuditLog::count());
    }

    /**
     * Test purge handles default category for uncategorized logs.
     */
    public function test_purge_handles_uncategorized_logs(): void
    {
        // Create old logs with null category (default: 365 days retention)
        $this->createOldAuditLogs(null, 400, 2);

        // Create old logs with unknown category
        $this->createOldAuditLogs('unknown_category', 400, 1);

        $this->artisan('audit:purge', ['--force' => true])
            ->assertSuccessful();

        $this->assertEquals(0, AuditLog::count());
    }

    /**
     * Test different retention periods are respected.
     */
    public function test_retention_periods_are_respected(): void
    {
        // auth_failed: 90 days - 95 days old should be deleted
        $this->createOldAuditLogs('auth_failed', 95, 1);

        // auth: 180 days - 95 days old should NOT be deleted
        $this->createOldAuditLogs('auth', 95, 1);

        // member: 365 days - 95 days old should NOT be deleted
        $this->createOldAuditLogs('member', 95, 1);

        $this->artisan('audit:purge', ['--force' => true])
            ->assertSuccessful();

        // Only auth_failed should be deleted
        $this->assertEquals(0, AuditLog::where('event_category', 'auth_failed')->count());
        $this->assertEquals(1, AuditLog::where('event_category', 'auth')->count());
        $this->assertEquals(1, AuditLog::where('event_category', 'member')->count());
    }

    /**
     * Helper to create audit logs with a specific age.
     */
    protected function createOldAuditLogs(?string $category, int $daysOld, int $count): void
    {
        $createdAt = Carbon::now()->subDays($daysOld);

        for ($i = 0; $i < $count; $i++) {
            $log = AuditLog::create([
                'event' => $category ? "{$category}_test_event" : 'uncategorized_event',
                'event_category' => $category,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit Test',
                'payload' => ['test' => true],
            ]);

            // Force update timestamps (Eloquent overrides created_at in create())
            AuditLog::where('id', $log->id)->update([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
