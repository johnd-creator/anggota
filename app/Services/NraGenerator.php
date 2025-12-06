<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Facades\DB;

class NraGenerator
{
    public static function generate(int $unitCode, int $joinYear): array
    {
        return DB::transaction(function () use ($unitCode, $joinYear) {
            $yearTwoDigit = (int) substr((string) $joinYear, -2);
            $sequence = Member::where('organization_unit_id', $unitCode)
                ->where('join_year', $joinYear)
                ->lockForUpdate()
                ->max('sequence_number');

            $nextSeq = ($sequence ?? 0) + 1;
            $nra = sprintf('%03d-%02d-%03d', $unitCode, $yearTwoDigit, $nextSeq);

            return ['nra' => $nra, 'sequence' => $nextSeq];
        });
    }
}

