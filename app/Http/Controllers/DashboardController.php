<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if ($user && $user->role && $user->role->name === 'reguler') {
            return redirect()->route('itworks');
        }

        $isGlobal = $user?->hasGlobalAccess() ?? false;
        $unitId = $user?->currentUnitId();

        return Inertia::render('Dashboard', [
            'dashboard' => [
                'members_by_unit' => $this->getMembersByUnit($user, $isGlobal, $unitId),
                'growth_last_12' => $this->getGrowthStats($user, $isGlobal, $unitId),
                'mutations' => $this->getMutationStats($user, $isGlobal, $unitId),
                'recent_mutations' => $this->getRecentMutations($user, $isGlobal, $unitId),
                'recent_activities' => $this->getRecentActivity($user, $isGlobal, $unitId),
            ],
            'alerts' => $this->getAlerts($user, $isGlobal, $unitId),
            'dues_summary' => $this->getDuesSummary($user),
            'unpaid_members' => $this->getUnpaidMembers($user),
            'finance' => $this->getFinanceData($user),
            'letters' => $this->getLettersSummary($user),
            'announcements_pinned' => $this->getPinnedAnnouncements($user),
            'my_dues' => $this->getMyDuesSummary($user),
        ]);
    }

    private function getRecentMutations($user, bool $isGlobal, ?int $unitId)
    {
        $query = \App\Models\MutationRequest::with([
            'member:id,full_name',
            'fromUnit:id,name',
            'toUnit:id,name',
        ])->where('status', 'pending');

        if (! $isGlobal && $unitId) {
            $query->where(function ($q) use ($unitId) {
                $q->where('from_unit_id', $unitId)
                    ->orWhere('to_unit_id', $unitId);
            });
        }

        return $query->latest()
            ->take(5)
            ->get()
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'member_name' => $m->member?->full_name ?? 'Unknown',
                    'from_unit' => $m->fromUnit?->name,
                    'to_unit' => $m->toUnit?->name,
                    'type' => 'Mutasi',
                    'date' => $m->created_at->toIso8601String(),
                    'status' => $m->status,
                    'status_label' => ucfirst($m->status),
                ];
            });
    }

    private function getRecentActivity($user, bool $isGlobal, ?int $unitId)
    {
        // Don't show audit logs to regular members to avoid info leak confusion,
        // though UI hides the section anyway.
        if ($user->hasRole('anggota') || $user->hasRole('reguler')) {
            return [];
        }

        $query = \App\Models\AuditLog::with('user:id,name');

        if (! $isGlobal && $unitId) {
            $query->where('organization_unit_id', $unitId);
        }

        return $query->latest()
            ->take(5)
            ->get()
            ->map(function ($log) {
                $message = "{$log->event_category}: {$log->event}";
                $userName = $log->user?->name;

                // Basic message formatting
                if ($userName) {
                    $message = "{$userName} - {$log->event}";
                }

                // Map event to type for icon color
                $type = 'info';
                if (in_array($log->event, ['login_failed', 'unauthorized', 'breach'])) {
                    $type = 'error';
                }
                if (in_array($log->event, ['login', 'create', 'update', 'approve'])) {
                    $type = 'success';
                }
                if (in_array($log->event, ['delete', 'revoke'])) {
                    $type = 'warning';
                }

                return [
                    'message' => $message,
                    'time' => $log->created_at->diffForHumans(),
                    'type' => $type,
                ];
            });
    }

    private function getPinnedAnnouncements($user)
    {
        // Skip query if announcements feature is disabled
        if (! config('features.announcements', true)) {
            return collect([]);
        }

        $query = \App\Models\Announcement::query()
            ->visibleTo($user)
            ->where('pin_to_dashboard', true)
            ->with([
                'organizationUnit:id,name',
                'attachments:id,announcement_id,original_name',
            ])
            ->latest()
            ->take(5);

        $query->whereDoesntHave('dismissals', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });

        return $query->get()
            ->map(function ($announcement) {
                return [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'body_snippet' => \Illuminate\Support\Str::limit($announcement->body, 150),
                    'scope_type' => $announcement->scope_type,
                    'organization_unit_name' => $announcement->organizationUnit?->name,
                    'created_at' => $announcement->created_at,
                    'attachments' => $announcement->attachments->map(fn ($file) => [
                        'id' => $file->id,
                        'original_name' => $file->original_name,
                        'download_url' => $file->download_url,
                    ]),
                ];
            });
    }

    private function getLettersSummary($user)
    {
        if (! $user) {
            return null;
        }

        $roleName = $user->role?->name;
        if (! in_array($roleName, ['super_admin', 'admin_pusat', 'admin_unit', 'anggota', 'bendahara', 'pengurus'], true)) {
            return null;
        }

        $inboxBase = $this->getInboxBaseQuery($user);
        if (! $inboxBase) {
            return [
                'unread' => 0,
                'this_month' => 0,
                'urgent' => 0,
                'secret' => 0,
                'drafts' => 0,
                'approvals' => 0,
            ];
        }

        $startMonth = now()->startOfMonth();

        // OPTIMIZED: Combine 4 count queries into 1 aggregated query
        $stats = (clone $inboxBase)
            ->selectRaw("
                COUNT(CASE WHEN NOT EXISTS (
                    SELECT 1 FROM letter_reads 
                    WHERE letter_reads.letter_id = letters.id 
                    AND letter_reads.user_id = ?
                ) THEN 1 END) as unread,
                COUNT(CASE WHEN created_at >= ? THEN 1 END) as this_month,
                COUNT(CASE WHEN urgency IN ('segera', 'kilat') THEN 1 END) as urgent,
                COUNT(CASE WHEN confidentiality = 'rahasia' THEN 1 END) as secret
            ", [$user->id, $startMonth])
            ->first();

        $drafts = 0;
        if (in_array($roleName, ['super_admin', 'admin_unit', 'admin_pusat', 'pengurus'], true)) {
            $drafts = Letter::where('creator_user_id', $user->id)
                ->whereIn('status', ['draft', 'revision'])
                ->count();
        }

        $approvals = 0;
        if ($roleName === 'super_admin') {
            $approvals = Letter::needsApproval()->count();
        } else {
            $positionName = strtolower((string) $user->getUnionPositionName());
            $unitId = $user->currentUnitId();
            if (in_array($positionName, ['ketua', 'sekretaris'], true) && $unitId) {
                $approvals = Letter::needsApproval()
                    ->where('signer_type', $positionName)
                    ->where('from_unit_id', $unitId)
                    ->count();
            }
        }

        return [
            'unread' => (int) ($stats->unread ?? 0),
            'this_month' => (int) ($stats->this_month ?? 0),
            'urgent' => (int) ($stats->urgent ?? 0),
            'secret' => (int) ($stats->secret ?? 0),
            'drafts' => (int) $drafts,
            'approvals' => (int) $approvals,
        ];
    }

    private function getInboxBaseQuery($user)
    {
        if (! $user) {
            return null;
        }

        $roleName = $user->role?->name;
        $unitId = $user->currentUnitId();

        $query = Letter::query()
            ->whereIn('status', ['submitted', 'approved', 'sent', 'archived']);

        if (in_array($roleName, ['anggota', 'bendahara'], true)) {
            $hasAnyRecipient = (bool) ($user->member_id || $unitId);
            if (! $hasAnyRecipient) {
                return $query->whereRaw('1=0');
            }

            return $query->where(function ($q) use ($user, $unitId) {
                if ($user->member_id) {
                    $q->orWhere(function ($sub) use ($user) {
                        $sub->where('to_type', 'member')->where('to_member_id', $user->member_id);
                    });
                }

                if ($unitId) {
                    $q->orWhere(function ($sub) use ($unitId) {
                        $sub->where('to_type', 'unit')->where('to_unit_id', $unitId);
                    });
                }
            });
        }

        if ($roleName === 'admin_unit') {
            if (! $unitId) {
                return $query->whereRaw('1=0');
            }

            return $query->where('to_type', 'unit')->where('to_unit_id', $unitId);
        }

        // admin_pusat & super_admin: inbox is letters addressed to admin_pusat
        if (in_array($roleName, ['admin_pusat', 'super_admin'], true)) {
            return $query->where('to_type', 'admin_pusat');
        }

        return $query->whereRaw('1=0');
    }

    private function getMembersByUnit($user, bool $isGlobal, ?int $unitId)
    {
        if ($isGlobal) {
            $cacheKey = \App\Services\CacheService::dashboardKey('members_by_unit', 'global');

            return \App\Services\CacheService::remember(
                $cacheKey,
                \App\Services\CacheService::TTL_MEDIUM,
                [\App\Services\CacheService::TAG_DASHBOARD, \App\Services\CacheService::TAG_UNITS, \App\Services\CacheService::TAG_MEMBERS],
                function () {
                    return \App\Models\OrganizationUnit::select('id', 'name')
                        ->withCount([
                            'members as active_members_count' => function ($q) {
                                $q->where('status', 'aktif');
                            },
                        ])
                        ->orderByDesc('active_members_count')
                        ->limit(10)
                        ->get();
                }
            );
        }

        // Non-global: only their own unit
        if (! $unitId) {
            return collect([]);
        }

        $cacheKey = \App\Services\CacheService::dashboardKey('members_by_unit', "unit_{$unitId}");

        return \App\Services\CacheService::remember(
            $cacheKey,
            \App\Services\CacheService::TTL_MEDIUM,
            [\App\Services\CacheService::TAG_DASHBOARD, \App\Services\CacheService::TAG_UNITS, \App\Services\CacheService::TAG_MEMBERS],
            function () use ($unitId) {
                return \App\Models\OrganizationUnit::select('id', 'name')
                    ->where('id', $unitId)
                    ->withCount([
                        'members as active_members_count' => function ($q) {
                            $q->where('status', 'aktif');
                        },
                    ])
                    ->get();
            }
        );
    }

    private function getGrowthStats($user, bool $isGlobal, ?int $unitId)
    {
        $months = collect(range(0, 11))->map(function ($i) {
            return now()->subMonths(11 - $i)->format('Y-m');
        });

        $scope = $isGlobal ? 'global' : "unit_{$unitId}";
        $cacheKey = \App\Services\CacheService::dashboardKey('growth_last_12', $scope);

        return \App\Services\CacheService::remember(
            $cacheKey,
            \App\Services\CacheService::TTL_MEDIUM,
            [\App\Services\CacheService::TAG_DASHBOARD, \App\Services\CacheService::TAG_MEMBERS],
            function () use ($months, $isGlobal, $unitId) {
                return $months->map(function ($m) use ($isGlobal, $unitId) {
                    $date = \Carbon\Carbon::createFromFormat('Y-m', $m);
                    $start = $date->copy()->startOfMonth()->toDateString();
                    $end = $date->copy()->endOfMonth()->toDateString();

                    $query = \App\Models\Member::whereDate('join_date', '>=', $start)
                        ->whereDate('join_date', '<=', $end);

                    if (! $isGlobal && $unitId) {
                        $query->where('organization_unit_id', $unitId);
                    }

                    return ['label' => $m, 'value' => (int) $query->count()];
                });
            }
        );
    }

    private function getMutationStats($user, bool $isGlobal, ?int $unitId)
    {
        $scope = $isGlobal ? 'global' : "unit_{$unitId}";
        $cacheKey = \App\Services\CacheService::dashboardKey('mutations_stats', $scope);

        return \App\Services\CacheService::remember(
            $cacheKey,
            \App\Services\CacheService::TTL_SHORT,
            [\App\Services\CacheService::TAG_DASHBOARD],
            function () use ($isGlobal, $unitId) {
                $baseQuery = \App\Models\MutationRequest::query();

                if (! $isGlobal && $unitId) {
                    $baseQuery->where(fn ($q) => $q->where('from_unit_id', $unitId)->orWhere('to_unit_id', $unitId));
                }

                return [
                    'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
                    'approved' => (clone $baseQuery)->where('status', 'approved')->count(),
                    'breach' => (clone $baseQuery)->where('sla_status', 'breach')->count(),
                ];
            }
        );
    }

    private function getAlerts($user, bool $isGlobal, ?int $unitId)
    {
        $scope = $isGlobal ? 'global' : "unit_{$unitId}";
        $cacheKey = \App\Services\CacheService::dashboardKey('alerts', $scope);

        return \App\Services\CacheService::remember(
            $cacheKey,
            \App\Services\CacheService::TTL_SHORT,
            [\App\Services\CacheService::TAG_DASHBOARD, \App\Services\CacheService::TAG_MEMBERS],
            function () use ($isGlobal, $unitId) {
                // Documents missing - scoped
                $docQuery = \App\Models\Member::query();
                if (! $isGlobal && $unitId) {
                    $docQuery->where('organization_unit_id', $unitId);
                }
                $docMissing = (clone $docQuery)->where(fn ($q) => $q->whereNull('photo_path')->orWhereNull('documents'))->count();

                // Login fail alerts - only for global users (security-wide metric)
                $loginFailSameIp = 0;
                if ($isGlobal) {
                    $loginFailSameIp = \App\Models\AuditLog::where('event', 'login_failed')
                        ->select(DB::raw('ip_address'), DB::raw('count(*) as c'))
                        ->groupBy('ip_address')->having(DB::raw('count(*)'), '>=', 5)->count();
                }

                // Mutations SLA breach - scoped
                $slaQuery = \App\Models\MutationRequest::where('sla_status', 'breach');
                if (! $isGlobal && $unitId) {
                    $slaQuery->where(fn ($q) => $q->where('from_unit_id', $unitId)->orWhere('to_unit_id', $unitId));
                }
                $slaBreached = $slaQuery->count();

                return [
                    'documents_missing' => $docMissing,
                    'login_fail_same_ip' => $loginFailSameIp,
                    'mutations_sla_breach' => $slaBreached,
                ];
            }
        );
    }

    private function getDuesSummary($user)
    {
        $roleName = $user->role?->name;
        if (in_array($roleName, ['admin_unit', 'bendahara', 'pengurus'], true)) {
            $unitId = $user->currentUnitId();

            return \App\Http\Controllers\Finance\FinanceDuesController::getDashboardSummary($unitId);
        } elseif (in_array($roleName, ['super_admin', 'admin_pusat'], true)) {
            return \App\Http\Controllers\Finance\FinanceDuesController::getDashboardSummary();
        }

        return null;
    }

    private function getUnpaidMembers($user)
    {
        $roleName = $user->role?->name;
        if (in_array($roleName, ['admin_unit', 'bendahara', 'pengurus'], true)) {
            $unitId = $user->currentUnitId();

            return \App\Http\Controllers\Finance\FinanceDuesController::getUnpaidMembers($unitId, null, 20);
        } elseif (in_array($roleName, ['super_admin', 'admin_pusat'], true)) {
            return \App\Http\Controllers\Finance\FinanceDuesController::getUnpaidMembers(null, null, 20);
        }

        return [];
    }

    private function getFinanceData($user)
    {
        $roleName = $user->role?->name;
        if (! in_array($roleName, ['admin_unit', 'bendahara', 'super_admin', 'pengurus'], true)) {
            return null;
        }

        $financeUnitId = ($roleName === 'super_admin') ? null : $user->currentUnitId();

        // 1. Current Balance
        $balance = \App\Models\FinanceLedger::query()
            ->when($financeUnitId, fn ($q) => $q->where('organization_unit_id', $financeUnitId))
            ->where('status', 'approved')
            ->selectRaw("SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END) as balance")
            ->value('balance') ?? 0;

        // 2. YTD Chart Data (OPTIMIZED: Single GROUP BY query instead of 12 queries)
        $ytdStats = \App\Models\FinanceLedger::query()
            ->when($financeUnitId, fn ($q) => $q->where('organization_unit_id', $financeUnitId))
            ->where('status', 'approved')
            ->whereYear('date', now()->year)
            ->selectRaw("
                DATE_FORMAT(date, '%Y-%m') as month_key,
                SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as expense
            ")
            ->groupBy('month_key')
            ->orderBy('month_key')
            ->get()
            ->keyBy('month_key');

        // Build complete 12-month data with zeros for missing months
        $ytdData = collect(range(0, 11))->map(function ($i) use ($ytdStats) {
            $date = now()->subMonths(11 - $i);
            $monthKey = $date->format('Y-m');
            $stats = $ytdStats->get($monthKey);

            return [
                'month' => $date->format('M Y'),
                'income' => (float) ($stats->income ?? 0),
                'expense' => (float) ($stats->expense ?? 0),
            ];
        });

        // 3. Recent Transactions
        $recent = \App\Models\FinanceLedger::query()
            ->when($financeUnitId, fn ($q) => $q->where('organization_unit_id', $financeUnitId))
            ->with('category:id,name')
            ->latest('date')
            ->limit(10)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'date' => $l->date->format('Y-m-d'),
                'description' => $l->description ?: $l->category->name,
                'type' => $l->type,
                'amount' => $l->amount,
                'status' => $l->status,
            ]);

        return [
            'balance' => $balance,
            'ytd' => $ytdData,
            'recent' => $recent,
            'unit_name' => $financeUnitId ? optional(\App\Models\OrganizationUnit::find($financeUnitId))->name : 'Global',
        ];
    }

    /**
     * Get personal dues summary for user's dashboard.
     */
    private function getMyDuesSummary($user)
    {
        // Skip if finance feature is disabled
        if (! config('features.finance', true)) {
            return null;
        }

        $memberId = $user?->member_id;
        if (! $memberId) {
            return null;
        }

        $member = \App\Models\Member::find($memberId);
        $joinDate = $member && $member->join_date ? \Carbon\Carbon::parse($member->join_date)->startOfMonth() : null;

        $currentPeriod = now()->format('Y-m');
        $periods = collect(range(0, 5))->map(fn ($i) => now()->subMonths($i)->format('Y-m'))
            ->filter(function ($period) use ($joinDate) {
                if (! $joinDate) {
                    return true;
                }

                return \Carbon\Carbon::createFromFormat('Y-m', $period)->endOfMonth()->gte($joinDate);
            })
            ->values()
            ->toArray();

        $payments = \App\Models\DuesPayment::where('member_id', $memberId)
            ->whereIn('period', $periods)
            ->get()
            ->keyBy('period');

        $unpaidPeriods = collect($periods)->filter(fn ($p) => ! $payments->has($p) || $payments->get($p)->status !== 'paid')->values();

        $currentStatus = 'unpaid';
        if ($payments->has($currentPeriod) && $payments->get($currentPeriod)->status === 'paid') {
            $currentStatus = 'paid';
        }

        return [
            'current_period' => $currentPeriod,
            'current_status' => $currentStatus,
            'unpaid_count' => $unpaidPeriods->count(),
            'unpaid_periods' => $unpaidPeriods->take(3)->values(),
        ];
    }
}
