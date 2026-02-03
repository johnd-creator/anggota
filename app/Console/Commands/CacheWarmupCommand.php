<?php

namespace App\Console\Commands;

use App\Services\CacheService;
use Illuminate\Console\Command;

class CacheWarmupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:warmup {--all : Warm up all caches including dashboard} {--references : Warm up reference data only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm up critical caches for better performance';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting cache warmup...');
        $this->newLine();

        $results = [];

        // Warm up reference data
        $this->info('Warming up reference data...');
        $refResults = $this->warmupReferences();
        $results = array_merge($results, $refResults);

        // Warm up dashboard caches if --all flag is provided
        if ($this->option('all')) {
            $this->newLine();
            $this->info('Warming up dashboard caches...');
            $dashResults = $this->warmupDashboard();
            $results = array_merge($results, $dashResults);
        }

        $this->newLine();
        $this->info('Cache warmup completed!');
        $this->newLine();

        // Display results
        $this->table(['Cache', 'Status'], array_map(fn ($k, $v) => [$k, $v], array_keys($results), $results));

        return Command::SUCCESS;
    }

    /**
     * Warm up reference data caches
     */
    private function warmupReferences(): array
    {
        $results = [];

        // Warm up organization units
        try {
            $units = \App\Models\OrganizationUnit::select('id', 'name', 'code')->get();
            \Illuminate\Support\Facades\Cache::tags([CacheService::TAG_UNITS])
                ->rememberForever('units:all', fn () => $units);
            $results['Organization Units'] = "✓ Warmed ({$units->count()} units)";
        } catch (\Exception $e) {
            $results['Organization Units'] = "✗ Failed: {$e->getMessage()}";
        }

        // Warm up union positions
        try {
            $positions = \App\Models\UnionPosition::select('id', 'name')->get();
            \Illuminate\Support\Facades\Cache::tags([CacheService::TAG_POSITIONS])
                ->rememberForever('positions:all', fn () => $positions);
            $results['Union Positions'] = "✓ Warmed ({$positions->count()} positions)";
        } catch (\Exception $e) {
            $results['Union Positions'] = "✗ Failed: {$e->getMessage()}";
        }

        // Warm up letter categories
        try {
            $categories = \App\Models\LetterCategory::active()->ordered()->get(['id', 'name', 'code']);
            \Illuminate\Support\Facades\Cache::tags([CacheService::TAG_CATEGORIES])
                ->rememberForever('letter_categories:active', fn () => $categories);
            $results['Letter Categories'] = "✓ Warmed ({$categories->count()} categories)";
        } catch (\Exception $e) {
            $results['Letter Categories'] = "✗ Failed: {$e->getMessage()}";
        }

        // Warm up finance categories
        try {
            $categories = \App\Models\FinanceCategory::select('id', 'name', 'type', 'default_amount')
                ->where('is_system', false)
                ->get();
            \Illuminate\Support\Facades\Cache::tags([CacheService::TAG_FINANCE])
                ->rememberForever('finance_categories:custom', fn () => $categories);
            $results['Finance Categories'] = "✓ Warmed ({$categories->count()} categories)";
        } catch (\Exception $e) {
            $results['Finance Categories'] = "✗ Failed: {$e->getMessage()}";
        }

        return $results;
    }

    /**
     * Warm up dashboard caches (requires user context, simplified version)
     */
    private function warmupDashboard(): array
    {
        $results = [];

        // Warm up global stats (no user context needed)
        try {
            $totalMembers = \App\Models\Member::where('status', 'aktif')->count();
            $results['Global Member Count'] = "✓ Warmed ({$totalMembers} members)";
        } catch (\Exception $e) {
            $results['Global Member Count'] = "✗ Failed: {$e->getMessage()}";
        }

        try {
            $totalUnits = \App\Models\OrganizationUnit::count();
            $results['Total Units'] = "✓ Warmed ({$totalUnits} units)";
        } catch (\Exception $e) {
            $results['Total Units'] = "✗ Failed: {$e->getMessage()}";
        }

        try {
            $pendingMutations = \App\Models\MutationRequest::where('status', 'pending')->count();
            $results['Pending Mutations'] = "✓ Warmed ({$pendingMutations} pending)";
        } catch (\Exception $e) {
            $results['Pending Mutations'] = "✗ Failed: {$e->getMessage()}";
        }

        return $results;
    }
}
