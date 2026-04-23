<?php

namespace App\Services;

use App\Models\Member;
use App\Models\OrganizationUnit;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class KtaGenerator
{
    public static function generate(int $unitId, int $joinYear): array
    {
        return DB::transaction(function () use ($unitId, $joinYear) {
            $unit = OrganizationUnit::query()->findOrFail($unitId);
            $unitCode = strtoupper(trim((string) $unit->code));

            if (! preg_match('/^\d{3}$/', $unitCode)) {
                throw new RuntimeException("Unit {$unitId} tidak memiliki kode numerik 3 digit untuk KTA.");
            }

            $yearTwoDigit = (int) substr((string) $joinYear, -2);
            $sequence = Member::where('organization_unit_id', $unitId)
                ->lockForUpdate()
                ->max('sequence_number');

            $nextSeq = ($sequence ?? 0) + 1;
            $kta = sprintf('%s-%s-%02d%03d', $unitCode, 'SPPIPS', $yearTwoDigit, $nextSeq);

            return ['kta' => $kta, 'sequence' => $nextSeq];
        });
    }
}
