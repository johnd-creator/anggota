<?php

namespace App\Services;

use App\Models\DuesPayment;
use App\Models\FinanceCategory;
use App\Models\FinanceLedger;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DuesService
{
    /**
     * Record payment for multiple members in a batch.
     * Creates dues_payments records and a single aggregate ledger entry.
     *
     * @param array $memberIds Member IDs to mark as paid
     * @param string $period Period in YYYY-MM format
     * @param int $categoryId Finance category ID for ledger entry
     * @param float $amount Amount per member
     * @param string|null $notes Optional notes
     * @param User $user The user recording the payment
     * @return array ['success' => int, 'skipped' => int, 'ledger_id' => int|null]
     */
    public function recordPaymentBatch(
        array $memberIds,
        string $period,
        int $categoryId,
        float $amount,
        ?string $notes,
        User $user
    ): array {
        $result = ['success' => 0, 'skipped' => 0, 'ledger_id' => null];

        if (empty($memberIds) || $amount <= 0) {
            return $result;
        }

        // Get category for ledger description
        $category = FinanceCategory::find($categoryId);
        if (!$category) {
            return $result;
        }

        // Determine unit scope
        $isSuperAdmin = $user->hasRole('super_admin');
        $userUnitId = $user->organization_unit_id;

        // Get valid members (active, within unit scope)
        $membersQuery = Member::whereIn('id', $memberIds)
            ->where('status', 'aktif');

        if (!$isSuperAdmin && $userUnitId) {
            $membersQuery->where('organization_unit_id', $userUnitId);
        }

        $validMembers = $membersQuery->get();

        if ($validMembers->isEmpty()) {
            return $result;
        }

        // Get already paid member IDs for this period
        $alreadyPaidIds = DuesPayment::whereIn('member_id', $validMembers->pluck('id'))
            ->where('period', $period)
            ->where('status', 'paid')
            ->pluck('member_id')
            ->toArray();

        // Filter to only unpaid members
        $unpaidMembers = $validMembers->filter(function ($m) use ($alreadyPaidIds) {
            return !in_array($m->id, $alreadyPaidIds);
        });

        $result['skipped'] = count($alreadyPaidIds);

        if ($unpaidMembers->isEmpty()) {
            return $result;
        }

        DB::transaction(function () use ($unpaidMembers, $period, $categoryId, $amount, $notes, $user, $category, &$result) {
            $now = now();
            $successCount = 0;
            $processedUnitId = null;

            // Create/update dues_payments for each member
            foreach ($unpaidMembers as $member) {
                DuesPayment::updateOrCreate(
                    [
                        'member_id' => $member->id,
                        'period' => $period,
                    ],
                    [
                        'organization_unit_id' => $member->organization_unit_id,
                        'status' => 'paid',
                        'amount' => $amount,
                        'paid_at' => $now,
                        'notes' => $notes,
                        'recorded_by' => $user->id,
                    ]
                );
                $successCount++;
                $processedUnitId = $processedUnitId ?? $member->organization_unit_id;
            }

            $result['success'] = $successCount;

            // Create single aggregate ledger entry
            if ($successCount > 0) {
                $totalAmount = $amount * $successCount;
                $memberNames = $unpaidMembers->take(3)->pluck('full_name')->join(', ');
                $moreCount = max(0, $successCount - 3);

                $description = "Iuran {$period}: {$successCount} anggota";
                if ($notes) {
                    $description .= " - {$notes}";
                }

                // Determine unit for ledger (use first member's unit, or user's unit)
                $ledgerUnitId = $processedUnitId ?? $user->organization_unit_id;

                $ledger = FinanceLedger::create([
                    'organization_unit_id' => $ledgerUnitId,
                    'finance_category_id' => $categoryId,
                    'type' => 'income',
                    'amount' => $totalAmount,
                    'description' => $description,
                    'date' => $now->toDateString(),
                    'status' => FinanceLedger::workflowEnabled() ? 'submitted' : 'approved',
                    'created_by' => $user->id,
                ]);

                $result['ledger_id'] = $ledger->id;
            }
        });

        return $result;
    }

    /**
     * Record single payment (for backward compatibility).
     */
    public function recordSinglePayment(
        int $memberId,
        string $period,
        string $status,
        ?float $amount,
        ?string $notes,
        User $user
    ): bool {
        $member = Member::find($memberId);
        if (!$member) {
            return false;
        }

        // Check unit access
        $isSuperAdmin = $user->hasRole('super_admin');
        if (!$isSuperAdmin && (int) $user->organization_unit_id !== (int) $member->organization_unit_id) {
            return false;
        }

        $data = [
            'organization_unit_id' => $member->organization_unit_id,
            'status' => $status,
            'notes' => $notes,
        ];

        if ($status === 'paid') {
            $data['amount'] = $amount;
            $data['paid_at'] = now();
            $data['recorded_by'] = $user->id;
        } else {
            $data['amount'] = null;
            $data['paid_at'] = null;
            $data['recorded_by'] = null;
        }

        DuesPayment::updateOrCreate(
            ['member_id' => $memberId, 'period' => $period],
            $data
        );

        return true;
    }
}
