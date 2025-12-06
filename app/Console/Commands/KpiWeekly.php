<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\KpiSnapshot;

class KpiWeekly extends Command
{
    protected $signature = 'kpi:weekly';
    protected $description = 'Hitung KPI mingguan dan simpan ke kpi_snapshots';

    public function handle(): int
    {
        try {
            $total = DB::table('members')->count();
            $complete = DB::table('members')->whereNotNull('address')->whereNotNull('phone')->whereNotNull('photo_path')->count();
            $completeness = $total > 0 ? round(($complete / $total) * 100, 2) : 0;

            $pending = DB::table('mutation_requests')->where('status','pending')->count();
            $breach = DB::table('mutation_requests')->where('status','pending')->where('created_at','<', now()->subDays(3))->count();
            $sla_avg = $pending === 0 ? 0 : ($breach / max($pending,1)) * 100; // proxy metric

            $cards_downloaded = DB::table('activity_logs')->where('action','card_pdf_download')->count();

            \App\Models\KpiSnapshot::create([
                'completeness_pct' => $completeness,
                'mutation_sla_breach_pct' => round($sla_avg, 2),
                'card_downloads' => $cards_downloaded,
                'calculated_at' => now(),
            ]);
            Log::info('kpi_weekly_success');
            $this->info('KPI snapshot created');
        } catch (\Throwable $e) {
            Log::error('kpi_weekly_failed', ['error' => $e->getMessage()]);
            $this->error('KPI calculation failed: ' . $e->getMessage());
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
