<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Member;
use Illuminate\Support\Str;

class GenerateMemberCards extends Command
{
    protected $signature = 'members:generate-cards {--dry-run}';
    protected $description = 'Generate QR token and card_valid_until for members that missing them';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        
        $members = Member::whereNull('qr_token')
            ->orWhereNull('card_valid_until')
            ->get();
        
        $count = 0;
        
        $this->info("Found {$members->count()} members missing QR token or card_valid_until");
        
        foreach ($members as $member) {
            $qrToken = Str::random(32);
            $cardValidUntil = now()->addYears(1)->toDateString();
            
            $this->info("Processing: {$member->full_name} (ID: {$member->id})");
            $this->info("  QR Token: {$qrToken}");
            $this->info("  Card Valid Until: {$cardValidUntil}");
            
            if (!$dry) {
                $member->qr_token = $qrToken;
                $member->card_valid_until = $cardValidUntil;
                $member->save();
                $count++;
            } else {
                $this->warn("  [DRY-RUN] Not saved");
            }
        }
        
        $this->info("\nTotal processed: {$count}");
        $this->info("  Dry-run: " . ($dry ? 'YES' : 'NO'));
        
        return self::SUCCESS;
    }
}
