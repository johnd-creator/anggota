<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if ($user && $user->role && $user->role->name === 'reguler') {
            return redirect()->route('itworks');
        }

        return Inertia::render('Dashboard', [
            'dashboard' => [
                'members_by_unit' => $this->getMembersByUnit(),
                'growth_last_12' => $this->getGrowthStats(),
                'mutations' => $this->getMutationStats(),
            ],
            'alerts' => $this->getAlerts(),
            'dues_summary' => $this->getDuesSummary($user),
            'unpaid_members' => $this->getUnpaidMembers($user),
            'finance' => $this->getFinanceData($user),
            'letters' => $this->getLettersSummary($user),
        ]);
    }

    private function getLettersSummary($user)
    {
        if (!$user || !Schema::hasTable('letters')) {
            return null;
        }

        $roleName = optional(optional($user)->role)->name;
        if (!in_array($roleName, ['super_admin', 'admin_pusat', 'admin_unit', 'anggota', 'bendahara'], true)) {
            return null;
        }

        $inboxBase = $this->getInboxBaseQuery($user);
        if (!$inboxBase) {
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

        $unread = 0;
        if (Schema::hasTable('letter_reads')) {
            $unread = (clone $inboxBase)->unreadFor($user)->count();
        }

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
        if (in_array($roleName, ['super_admin', 'admin_unit', 'admin_pusat'], true)) {
            $drafts = Letter::where('creator_user_id', $user->id)
                ->whereIn('status', ['draft', 'revision'])
                ->count();
        }

        $approvals = 0;
        if ($roleName === 'super_admin') {
            $approvals = Letter::needsApproval()->count();
        } else {
            $positionName = strtolower((string) $user->getUnionPositionName());
            if (in_array($positionName, ['ketua', 'sekretaris'], true) && $user->organization_unit_id) {
                $approvals = Letter::needsApproval()
                    ->where('signer_type', $positionName)
                    ->where('from_unit_id', $user->organization_unit_id)
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
        if (!$user) {
            return null;
        }

        $roleName = optional(optional($user)->role)->name;

        $query = Letter::query()
            ->whereIn('status', ['submitted', 'approved', 'sent', 'archived']);

        if (in_array($roleName, ['anggota', 'bendahara'], true)) {
            $hasAnyRecipient = (bool) ($user->member_id || $user->organization_unit_id);
            if (!$hasAnyRecipient) {
                return $query->whereRaw('1=0');
            }

            return $query->where(function ($q) use ($user) {
                if ($user->member_id) {
                    $q->orWhere(function ($sub) use ($user) {
                        $sub->where('to_type', 'member')->where('to_member_id', $user->member_id);
                    });
                }

                if ($user->organization_unit_id) {
                    $q->orWhere(function ($sub) use ($user) {
                        $sub->where('to_type', 'unit')->where('to_unit_id', $user->organization_unit_id);
                    });
                }
            });
        }

        if ($roleName === 'admin_unit') {
            if (!$user->organization_unit_id) {
                return $query->whereRaw('1=0');
            }

            return $query->where('to_type', 'unit')->where('to_unit_id', $user->organization_unit_id);
        }

        // admin_pusat & super_admin: inbox is letters addressed to admin_pusat
        if (in_array($roleName, ['admin_pusat', 'super_admin'], true)) {
            return $query->where('to_type', 'admin_pusat');
        }

        return $query->whereRaw('1=0');
    }

    private function getMembersByUnit()
    {
        return Cache::remember('dash_members_by_unit', 300, function () {
            return \App\Models\OrganizationUnit::select('id', 'name')
                ->withCount([
                    'members as active_members_count' => function ($q) {
                        $q->where('status', 'aktif');
                    }
                ])
                ->orderByDesc('active_members_count')
                ->limit(10)
                ->get();
        });
    }

    private function getGrowthStats()
    {
        $months = collect(range(0, 11))->map(function ($i) {
            return now()->subMonths(11 - $i)->format('Y-m');
        });
        return Cache::remember('dash_growth_last_12', 300, function () use ($months) {
            return $months->map(function ($m) {
                $date = \Carbon\Carbon::createFromFormat('Y-m', $m);
                $start = $date->copy()->startOfMonth()->toDateString();
                $end = $date->copy()->endOfMonth()->toDateString();
                $count = \App\Models\Member::whereDate('join_date', '>=', $start)
                    ->whereDate('join_date', '<=', $end)
                    ->count();
                return ['label' => $m, 'value' => (int) $count];
            });
        });
    }

    private function getMutationStats()
    {
        return Cache::remember('dash_mutations_stats', 300, function () {
            return [
                'pending' => \App\Models\MutationRequest::where('status', 'pending')->count(),
                'approved' => \App\Models\MutationRequest::where('status', 'approved')->count(),
                'breach' => \App\Models\MutationRequest::where('sla_status', 'breach')->count(),
            ];
        });
    }

    private function getAlerts()
    {
        return Cache::remember('dash_alerts', 300, function () {
            $docMissing = \App\Models\Member::whereNull('photo_path')->orWhereNull('documents')->count();
            $loginFailSameIp = \App\Models\AuditLog::where('event', 'login_failed')
                ->select(DB::raw('ip_address'), DB::raw('count(*) as c'))
                ->groupBy('ip_address')->having(DB::raw('count(*)'), '>=', 5)->count();
            $slaBreached = \App\Models\MutationRequest::where('sla_status', 'breach')->count();
            return ['documents_missing' => $docMissing, 'login_fail_same_ip' => $loginFailSameIp, 'mutations_sla_breach' => $slaBreached];
        });
    }

    private function getDuesSummary($user)
    {
        $roleName = optional(optional($user)->role)->name;
        if (in_array($roleName, ['admin_unit', 'bendahara'], true)) {
            $unitId = $user->organization_unit_id;
            return \App\Http\Controllers\Finance\FinanceDuesController::getDashboardSummary($unitId);
        } elseif (in_array($roleName, ['super_admin', 'admin_pusat'], true)) {
            return \App\Http\Controllers\Finance\FinanceDuesController::getDashboardSummary();
        }
        return null;
    }

    private function getUnpaidMembers($user)
    {
        $roleName = optional(optional($user)->role)->name;
        if (in_array($roleName, ['admin_unit', 'bendahara'], true)) {
            $unitId = $user->organization_unit_id;
            return \App\Http\Controllers\Finance\FinanceDuesController::getUnpaidMembers($unitId, null, 20);
        } elseif (in_array($roleName, ['super_admin', 'admin_pusat'], true)) {
            return \App\Http\Controllers\Finance\FinanceDuesController::getUnpaidMembers(null, null, 20);
        }
        return [];
    }

    private function getFinanceData($user)
    {
        $roleName = optional(optional($user)->role)->name;
        if (!in_array($roleName, ['admin_unit', 'bendahara', 'super_admin'], true)) {
            return null;
        }

        $financeUnitId = ($roleName === 'super_admin') ? null : $user->organization_unit_id;

        // 1. Current Balance
        $balance = \App\Models\FinanceLedger::query()
            ->when($financeUnitId, fn($q) => $q->where('organization_unit_id', $financeUnitId))
            ->where('status', 'approved')
            ->selectRaw("SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END) as balance")
            ->value('balance') ?? 0;

        // 2. YTD Chart Data (Last 12 months)
        $ytdData = collect(range(0, 11))->map(function ($i) use ($financeUnitId) {
            $date = now()->subMonths(11 - $i);

            $start = $date->copy()->startOfMonth()->toDateString();
            $end = $date->copy()->endOfMonth()->toDateString();
            $stats = \App\Models\FinanceLedger::query()
                ->when($financeUnitId, fn($q) => $q->where('organization_unit_id', $financeUnitId))
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
            ->when($financeUnitId, fn($q) => $q->where('organization_unit_id', $financeUnitId))
            ->with('category:id,name') // Optimize eager load
            ->latest('date')
            ->limit(10)
            ->get()
            ->map(fn($l) => [
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
}
