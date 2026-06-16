<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\DuesPayment;
use App\Models\FinanceCategory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MemberDuesController extends Controller
{
    /**
     * Display member's dues history.
     */
    public function index()
    {
        $user = Auth::user();
        $memberId = $user->member_id;

        // Handle case where user has no linked member
        if (!$memberId) {
            return Inertia::render('Member/Dues', [
                'hasMember' => false,
                'payments' => [],
                'summary' => null,
            ]);
        }

        // Get dues payments for last 12 months
        $periods = $this->getLast12Periods();
        $currentPeriod = now()->format('Y-m');

        // Load member with join date
        $member = \App\Models\Member::find($memberId);
        $joinDate = $member->join_date ? Carbon::parse($member->join_date)->startOfMonth() : null;

        // Get current default amount from the recurring "Iuran Anggota" category
        $defaultAmount = FinanceCategory::where('is_recurring', true)
            ->where('type', 'income')
            ->where(function ($q) use ($member) {
                $q->whereNull('organization_unit_id');
                if ($member && $member->organization_unit_id) {
                    $q->orWhere('organization_unit_id', $member->organization_unit_id);
                }
            })
            ->orderByRaw('CASE WHEN organization_unit_id IS NOT NULL THEN 0 ELSE 1 END')
            ->value('default_amount') ?? (int) config('dues.default_amount', 30000);

        $payments = DuesPayment::where('member_id', $memberId)
            ->whereIn('period', $periods)
            ->orderByDesc('period')
            ->get()
            ->keyBy('period');

        // Build full list with virtual unpaid for missing periods
        $paymentsList = collect($periods)->map(function ($period) use ($payments, $joinDate, $defaultAmount) {
            // Check if user had not joined yet
            $periodDate = Carbon::createFromFormat('Y-m', $period)->endOfMonth();
            if ($joinDate && $periodDate->lt($joinDate)) {
                return null; // Skip periods before join
            }

            if ($payments->has($period)) {
                $p = $payments->get($period);
                return [
                    'period' => $period,
                    'status' => $p->status,
                    'amount' => (float) $p->amount,
                    'paid_at' => $p->paid_at?->format('d M Y'),
                    'notes' => $p->notes,
                ];
            }
            // No record = unpaid (not yet generated)
            return [
                'period' => $period,
                'status' => 'unpaid',
                'amount' => (float) $defaultAmount,
                'paid_at' => null,
                'notes' => null,
            ];
        })->filter()->values();

        // Calculate summary
        $unpaidPeriods = $paymentsList->filter(fn($p) => $p['status'] !== 'paid')->pluck('period')->values();
        $currentStatus = $paymentsList->firstWhere('period', $currentPeriod);

        return Inertia::render('Member/Dues', [
            'hasMember' => true,
            'payments' => $paymentsList,
            'summary' => [
                'current_period' => $currentPeriod,
                'current_status' => $currentStatus ? $currentStatus['status'] : 'unpaid',
                'unpaid_count' => $unpaidPeriods->count(),
                'unpaid_periods' => $unpaidPeriods->take(3)->values(),
            ],
            'default_amount' => (int) $defaultAmount,
        ]);
    }

    /**
     * Get last 12 periods (YYYY-MM format).
     *
     * @return string[]
     */
    protected function getLast12Periods(): array
    {
        $periods = [];
        for ($i = 0; $i < 12; $i++) {
            $periods[] = now()->subMonths($i)->format('Y-m');
        }
        return $periods;
    }
}
