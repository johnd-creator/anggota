<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Mobile\Concerns\ResolvesMobileMember;
use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\DuesPaymentResource;
use App\Models\DuesPayment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DuesController extends Controller
{
    use ResolvesMobileMember;

    public function index(Request $request): JsonResponse
    {
        $member = $this->mobileMember($request->user());

        if (! $member) {
            return response()->json([
                'has_member' => false,
                'payments' => [],
                'summary' => null,
            ]);
        }

        $periods = $this->last12Periods();
        $currentPeriod = now()->format('Y-m');
        $joinDate = $member->join_date ? Carbon::parse($member->join_date)->startOfMonth() : null;

        $payments = DuesPayment::where('member_id', $member->id)
            ->whereIn('period', $periods)
            ->orderByDesc('period')
            ->get()
            ->keyBy('period');

        $items = collect($periods)
            ->map(function ($period) use ($payments, $joinDate) {
                $periodDate = Carbon::createFromFormat('Y-m', $period)->endOfMonth();

                if ($joinDate && $periodDate->lt($joinDate)) {
                    return null;
                }

                if ($payments->has($period)) {
                    $payment = $payments->get($period);

                    return [
                        'period' => $period,
                        'status' => $payment->status,
                        'amount' => (float) $payment->amount,
                        'paid_at' => $payment->paid_at?->format('Y-m-d'),
                        'notes' => $payment->notes,
                    ];
                }

                return [
                    'period' => $period,
                    'status' => 'unpaid',
                    'amount' => (float) config('dues.default_amount', 30000),
                    'paid_at' => null,
                    'notes' => null,
                ];
            })
            ->filter()
            ->values();

        $unpaidPeriods = $items->filter(fn ($item) => $item['status'] !== 'paid')->pluck('period')->values();
        $currentStatus = $items->firstWhere('period', $currentPeriod);

        return response()->json([
            'has_member' => true,
            'payments' => DuesPaymentResource::collection($items),
            'summary' => [
                'current_period' => $currentPeriod,
                'current_status' => $currentStatus['status'] ?? 'unpaid',
                'unpaid_count' => $unpaidPeriods->count(),
                'unpaid_periods' => $unpaidPeriods->take(3)->values(),
            ],
            'default_amount' => (int) config('dues.default_amount', 30000),
        ]);
    }

    private function last12Periods(): array
    {
        $periods = [];

        for ($i = 0; $i < 12; $i++) {
            $periods[] = now()->subMonths($i)->format('Y-m');
        }

        return $periods;
    }
}
