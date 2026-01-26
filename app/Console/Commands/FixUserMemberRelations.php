<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Member;

class FixUserMemberRelations extends Command
{
    protected $signature = 'fix:user-member-relations {--dry-run}';
    protected $description = 'Fix user-member mismatch by linking Gmail users to members and deleting unused PLN users';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        
        $this->info('Analyzing user-member relations...');
        
        // Cari semua member
        $members = Member::whereNotNull('user_id')->get();
        
        $fixed = 0;
        $deleted = 0;
        $skipped = 0;
        
        foreach ($members as $member) {
            // Cek user yang terhubung ke member
            $currentUser = $member->user;
            
            if (!$currentUser) {
                $this->warn("Skipping Member {$member->id}: user not found");
                $skipped++;
                continue;
            }
            
            // Tentukan apakah ini User Gmail atau User PLN
            $isGmailUser = str_ends_with($currentUser->email, '@gmail.com');
            
            // Jika ini User Gmail, skip (sudah benar)
            if ($isGmailUser) {
                $this->info("Member {$member->id} ({$member->full_name}): User Gmail - OK");
                $skipped++;
                continue;
            }
            
            // Jika ini User PLN, cari User Gmail counterpart
            $plnEmail = $member->email;
            
            // Cari User Gmail yang punya company_email = email PLN atau punya member_id yang sama
            $gmailUser = User::where(function($query) use ($plnEmail, $member) {
                $query->where('company_email', $plnEmail)
                    ->orWhere(function($q) use ($member) {
                        $q->where('member_id', $member->id)
                            ->where('email', 'like', '%@gmail.com');
                    });
            })->where('email', 'like', '%@gmail.com')
                ->where('id', '!=', $currentUser->id)
                ->first();
            
            if ($gmailUser) {
                $this->info("Found mismatch:");
                $this->info("  Member: {$member->full_name} (ID: {$member->id})");
                $this->info("  Member.email: {$member->email}");
                $this->info("  User PLN: {$currentUser->email} (ID: {$currentUser->id})");
                $this->info("  User Gmail: {$gmailUser->email} (ID: {$gmailUser->id})");
                
                // Cek apakah member.user_id sudah benar
                if ($member->user_id !== $gmailUser->id) {
                    $this->warn("  Current member.user_id: {$member->user_id} (WRONG)");
                    $this->warn("  Will update to: {$gmailUser->id} (Gmail User)");
                    
                    if (!$dry) {
                        $member->user_id = $gmailUser->id;
                        $member->save();
                    }
                }
                
                // Cek apakah User Gmail sudah punya company_email
                if (!$gmailUser->company_email) {
                    $this->warn("  Will update Gmail user.company_email to: {$plnEmail}");
                    
                    if (!$dry) {
                        $gmailUser->company_email = $plnEmail;
                        $gmailUser->save();
                    }
                }
                
                // Delete User PLN (soft delete)
                $this->warn("  Will delete User PLN (ID: {$currentUser->id})");
                
                if (!$dry) {
                    $currentUser->delete();
                    $deleted++;
                }
                
                $fixed++;
            } else {
                $this->info("No Gmail user found for Member {$member->id} ({$member->full_name}) - OK");
                $skipped++;
            }
        }
        
        $this->info("\nSummary:");
        $this->info("  Fixed: {$fixed} mismatches");
        $this->info("  Deleted: {$deleted} PLN users");
        $this->info("  Skipped: {$skipped} users (already OK)");
        $this->info("  Dry-run: " . ($dry ? 'YES' : 'NO'));
        
        return self::SUCCESS;
    }
}
