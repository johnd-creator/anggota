<?php

namespace App\Services;

use App\Models\Letter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LetterNumberService
{
    /**
     * Assign a letter number to the given letter.
     * Format: SEQ/CATCODE/UNITABBR/SP-PIPS/MM/YYYY
     * Example: 001/UND/DPDTS/SP-PIPS/12/2025
     */
    public function assignNumber(Letter $letter, ?Carbon $date = null): Letter
    {
        $date = $date ?? now();

        return DB::transaction(function () use ($letter, $date) {
            $year = $date->year;
            $month = $date->month;

            // Get unit abbreviation (fallback to code, DPP for pusat if null)
            $letter->load('fromUnit', 'category');
            $unitAbbr = $letter->fromUnit?->abbreviation ?? $letter->fromUnit?->code ?? 'DPP';
            $catCode = $letter->category->code;

            // Get next sequence with lock to prevent race conditions
            $maxSeq = Letter::where('from_unit_id', $letter->from_unit_id)
                ->where('letter_category_id', $letter->letter_category_id)
                ->where('year', $year)
                ->lockForUpdate()
                ->max('sequence') ?? 0;

            $sequence = $maxSeq + 1;

            // Format: 001/UND/DPDTS/SP-PIPS/12/2025
            $letterNumber = sprintf(
                '%03d/%s/%s/SP-PIPS/%02d/%d',
                $sequence,
                $catCode,
                $unitAbbr,
                $month,
                $year
            );

            $letter->update([
                'sequence' => $sequence,
                'month' => $month,
                'year' => $year,
                'letter_number' => $letterNumber,
            ]);

            return $letter->fresh();
        });
    }
}
