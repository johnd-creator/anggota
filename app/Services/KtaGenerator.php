<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Facades\DB;

class KtaGenerator
{
    public static function generate(int $unitId, int $joinYear): array
    {
        return DB::transaction(function () use ($unitId, $joinYear) {
            $yearTwoDigit = (int) substr((string) $joinYear, -2);
            $sequence = Member::where('organization_unit_id', $unitId)
                ->where('join_year', $joinYear)
                ->lockForUpdate()
                ->max('sequence_number');

            $nextSeq = ($sequence ?? 0) + 1;
            $kta = sprintf('%03d-%s-%02d%03d', $unitId, 'SPPIPS', $yearTwoDigit, $nextSeq);

            return ['kta' => $kta, 'sequence' => $nextSeq];
        });
    }
}

