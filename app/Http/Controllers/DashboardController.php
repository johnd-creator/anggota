<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
        $query = \App\Models\MutationRequest::with(['member', 'fromUnit', 'toUnit'])
            ->where('status', 'pending');

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
                    'member_name' => $m->member->full_name ?? 'Unknown',
                    'type' => 'Mutasi', // Can be refined if we have different types
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

        $query = \App\Models\AuditLog::with('user');

        if (! $isGlobal && $unitId) {
            $query->where('organization_unit_id', $unitId);
        }

        return $query->latest()
            ->take(5)
            ->get()
            ->map(function ($log) {
                $message = "{$log->event_category}: {$log->event}";
                // Basic message formatting
                if ($log->user) {
                    $message = "{$log->user->name} - {$log->event}";
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
            ->with(['organizationUnit', 'attachments'])
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
        $endMonth = now()->endOfMonth();

        $unread = (clone $inboxBase)->unreadFor($user)->count();

        $thisMonth = (clone $inboxBase)
            ->whereBetween('created_at', [$startMonth, $endMonth])
            ->count();

        $urgent = (clone $inboxBase)
            ->whereIn('urgency', ['segera', 'kilat'])
            ->count();

        $secret = (clone $inboxBase)
            ->where('confidentiality', 'rahasia')
            ->count();

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
            'unread' => (int) $unread,
            'this_month' => (int) $thisMonth,
            'urgent' => (int) $urgent,
            'secret' => (int) $secret,
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
            return Cache::remember('dash_members_by_unit:global', 300, function () {
                return \App\Models\OrganizationUnit::select('id', 'name')
                    ->withCount([
                        'members as active_members_count' => function ($q) {
                            $q->where('status', 'aktif');
                        },
                    ])
                    ->orderByDesc('active_members_count')
                    ->limit(10)
                    ->get();
            });
        }

        // Non-global: only their own unit
        if (! $unitId) {
            return collect([]);
        }

        return Cache::remember("dash_members_by_unit:unit:{$unitId}", 300, function () use ($unitId) {
            return \App\Models\OrganizationUnit::select('id', 'name')
                ->where('id', $unitId)
                ->withCount([
                    'members as active_members_count' => function ($q) {
                        $q->where('status', 'aktif');
                    },
                ])
                ->get();
        });
    }

    private function getGrowthStats($user, bool $isGlobal, ?int $unitId)
    {
        $months = collect(range(0, 11))->map(function ($i) {
            return now()->subMonths(11 - $i)->format('Y-m');
        });

        $cacheKey = $isGlobal ? 'dash_growth_last_12:global' : "dash_growth_last_12:unit:{$unitId}";

        return Cache::remember($cacheKey, 300, function () use ($months, $isGlobal, $unitId) {
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
        });
    }

    private function getMutationStats($user, bool $isGlobal, ?int $unitId)
    {
        $cacheKey = $isGlobal ? 'dash_mutations_stats:global' : "dash_mutations_stats:unit:{$unitId}";

        return Cache::remember($cacheKey, 300, function () use ($isGlobal, $unitId) {
            $baseQuery = \App\Models\MutationRequest::query();

            if (! $isGlobal && $unitId) {
                $baseQuery->where(fn ($q) => $q->where('from_unit_id', $unitId)->orWhere('to_unit_id', $unitId));
            }

            return [
                'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
                'approved' => (clone $baseQuery)->where('status', 'approved')->count(),
                'breach' => (clone $baseQuery)->where('sla_status', 'breach')->count(),
            ];
        });
    }

    private function getAlerts($user, bool $isGlobal, ?int $unitId)
    {
        $cacheKey = $isGlobal ? 'dash_alerts:global' : "dash_alerts:unit:{$unitId}";

        return Cache::remember($cacheKey, 300, function () use ($isGlobal, $unitId) {
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
        });
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

        // 2. YTD Chart Data (Last 12 months)
        $ytdData = collect(range(0, 11))->map(function ($i) use ($financeUnitId) {
            $date = now()->subMonths(11 - $i);

            $start = $date->copy()->startOfMonth()->toDateString();
            $end = $date->copy()->endOfMonth()->toDateString();
            $stats = \App\Models\FinanceLedger::query()
                ->when($financeUnitId, fn ($q) => $q->where('organization_unit_id', $financeUnitId))
                ->where('status', 'approved')
                ->whereDate('date', '>=', $start)
                ->whereDate('date', '<=', $end)
                ->selectRaw("SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as income")
                ->selectRaw("SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as expense")
                ->first();

            return [
                'month' => $date->format('M Y'),
                'income' => (float) ($stats->income ?? 0),
                'expense' => (float) ($stats->expense ?? 0),
            ];
        });

        // 3. Recent Transactions
        $recent = \App\Models\FinanceLedger::query()
            ->when($financeUnitId, fn ($q) => $q->where('organization_unit_id', $financeUnitId))
            ->with('category:id,name') // Optimize eager load
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
