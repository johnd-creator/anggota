<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Member;

class MembersMigrateIds extends Command
{
    protected $signature = 'members:migrate-ids {--dry-run}';
    protected $description = 'Migrasikan NRA -> KTA (Smart ID) dan isi NIP default bila kosong';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $updated = 0;
        Member::orderBy('id')->chunk(200, function($rows) use (&$updated, $dry){
            foreach ($rows as $m) {
                $changed = false;
                if (!$m->kta_number) {
                    $yearTwo = (int) substr((string) $m->join_year, -2);
                    $m->kta_number = sprintf('%03d-%s-%02d%03d', $m->organization_unit_id, 'SPPIPS', $yearTwo, $m->sequence_number);
                    $changed = true;
                }
                if (!$m->nip) {
                    $m->nip = 'NIP-' . $m->id;
                    $changed = true;
                }
                if ($changed && !$dry) { $m->save(); $updated++; }
            }
        });
        $this->info('Updated members: ' . $updated);
        return self::SUCCESS;
    }
}

