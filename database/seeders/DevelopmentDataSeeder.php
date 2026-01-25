<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Aspiration;
use App\Models\AspirationCategory;
use App\Models\FinanceCategory;
use App\Models\FinanceLedger;
use App\Models\Letter;
use App\Models\LetterCategory;
use App\Models\Member;
use App\Models\MutationRequest;
use App\Models\OrganizationUnit;
use App\Models\PendingMember;
use App\Models\Role;
use App\Models\UnionPosition;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Comprehensive development data seeder
 * 
 * Generates 1000+ fake records across all modules.
 * Run with: php artisan db:seed --class=DevelopmentDataSeeder
 */
class DevelopmentDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 Starting comprehensive development data seeding...');

        // Get reference data
        $units = OrganizationUnit::all();
        $roles = Role::all();
        $unionPositions = UnionPosition::all();
        $posAnggota = $unionPositions->where('name', 'Anggota')->first();
        $posKetua = $unionPositions->where('name', 'Ketua')->first();
        $posSekretaris = $unionPositions->where('name', 'Sekretaris')->first();
        $posBendahara = $unionPositions->where('name', 'Bendahara')->first();

        $roleAnggota = $roles->where('name', 'anggota')->first();
        $roleAdmin = $roles->where('name', 'admin_unit')->first();

        if ($units->count() === 0) {
            $this->command->error('❌ No organization units found. Please run OrganizationUnitSeeder first.');
            return;
        }

        // ==================== STEP 1: Members (500) ====================
        $this->command->info('📝 Creating 500 members...');
        $members = [];
        foreach (range(1, 500) as $i) {
            $unit = $units->random();

            // 70% Anggota, 10% Ketua, 10% Sekretaris, 10% Bendahara
            $rand = rand(1, 100);
            if ($rand <= 70) {
                $position = $posAnggota;
            } elseif ($rand <= 80) {
                $position = $posKetua;
            } elseif ($rand <= 90) {
                $position = $posSekretaris;
            } else {
                $position = $posBendahara;
            }

            $ktaNumber = 'KTA-' . str_pad($i, 6, '0', STR_PAD_LEFT);
            $nraNumber = 'NRA-' . str_pad($i, 8, '0', STR_PAD_LEFT);
            $email = fake()->unique()->safeEmail();
            $nip = fake()->unique()->numerify('NIP-################');
            $joinDate = fake()->dateTimeBetween('-10 years', 'now')->format('Y-m-d');
            $joinYear = (int) date('Y', strtotime($joinDate));

            $conflict = Member::withTrashed()
                ->where('email', $email)
                ->orWhere('kta_number', $ktaNumber)
                ->orWhere('nra', $nraNumber)
                ->orWhere('nip', $nip)
                ->exists();

            if ($conflict) {
                continue;
            }

            $member = Member::create([
                'full_name' => fake()->name(),
                'email' => $email,
                'phone' => fake()->phoneNumber(),
                'birth_place' => fake()->city(),
                'birth_date' => fake()->date('Y-m-d', '-25 years'),
                'job_title' => fake()->jobTitle(),
                'employment_type' => fake()->randomElement(['organik', 'tkwt']),
                'status' => fake()->randomElement(['aktif', 'aktif', 'aktif', 'resign']), // 75% aktif
                'join_date' => $joinDate,
                'company_join_date' => fake()->dateTimeBetween('-15 years', 'now')->format('Y-m-d'),
                'organization_unit_id' => $unit->id,
                'union_position_id' => $position?->id,
                'kta_number' => $ktaNumber,
                'nra' => $nraNumber,
                'nip' => $nip,
                'join_year' => $joinYear,
                'sequence_number' => $i,
            ]);

            $members[] = $member;

            if ($i % 100 === 0) {
                $this->command->info("   ✓ Created {$i} members");
            }
        }

        // ==================== STEP 2: Users linked to some members (200) ====================
        $this->command->info('👤 Creating 200 users linked to members...');
        foreach (array_slice($members, 0, 200) as $member) {
            $user = User::firstOrCreate(
                ['email' => $member->email],
                [
                    'name' => $member->full_name,
                    'password' => bcrypt('password'),
                    'role_id' => $roleAnggota?->id,
                    'member_id' => $member->id,
                    'organization_unit_id' => $member->organization_unit_id,
                ]
            );

            if (!$user->member_id) {
                $user->member_id = $member->id;
                $user->organization_unit_id = $member->organization_unit_id;
                $user->save();
            }

            if (!$member->user_id) {
                $member->user_id = $user->id;
                $member->save();
            }
        }

        // ==================== STEP 3: Announcements (150) ====================
        $this->command->info('📢 Creating 150 announcements...');
        $admins = User::whereHas('role', fn($q) => $q->whereIn('name', ['super_admin', 'admin_pusat', 'admin_unit']))->get();
        $creator = $admins->isNotEmpty() ? $admins->random() : User::first();

        foreach (range(1, 150) as $i) {
            $scopeRand = rand(1, 100);
            if ($scopeRand <= 50) {
                $scope = 'global_all';
                $unitId = null;
            } elseif ($scopeRand <= 70) {
                $scope = 'global_officers';
                $unitId = null;
            } else {
                $scope = 'unit';
                $unitId = $units->random()->id;
            }

            Announcement::create([
                'title' => fake()->sentence(6),
                'body' => fake()->paragraphs(3, true),
                'scope_type' => $scope,
                'organization_unit_id' => $unitId,
                'is_active' => fake()->boolean(85), // 85% active
                'pin_to_dashboard' => fake()->boolean(20), // 20% pinned
                'created_by' => $creator->id,
                'created_at' => fake()->dateTimeBetween('-1 year', 'now'),
            ]);
        }

        // ==================== STEP 4: Aspirations (200) ====================
        $this->command->info('💡 Creating 200 aspirations...');
        $categories = AspirationCategory::all();
        $userMembers = User::whereNotNull('member_id')->get();

        foreach (range(1, 200) as $i) {
            if ($userMembers->isEmpty())
                break;

            $user = $userMembers->random();
            $member = Member::find($user->member_id);
            if (!$member || $categories->isEmpty()) {
                continue;
            }

            Aspiration::create([
                'title' => fake()->sentence(5),
                'body' => fake()->paragraphs(2, true),
                'category_id' => $categories->random()->id,
                'member_id' => $member->id,
                'user_id' => $user->id,
                'organization_unit_id' => $member->organization_unit_id,
                'status' => fake()->randomElement(['new', 'new', 'in_progress', 'resolved']),
                'created_at' => fake()->dateTimeBetween('-6 months', 'now'),
            ]);
        }

        // ==================== STEP 5: Letters (100) ====================
        $this->command->info('✉️ Creating 100 letters...');
        $letterCategories = LetterCategory::all();
        $senders = User::whereHas('role', fn($q) => $q->whereIn('name', ['admin_pusat', 'admin_unit']))->get();

        foreach (range(1, 100) as $i) {
            if ($senders->isEmpty())
                break;

            $sender = $senders->random();
            $toType = fake()->randomElement(['unit', 'member', 'admin_pusat']);

            $toUnitId = $toType === 'unit' ? $units->random()->id : null;
            $toMemberId = $toType === 'member' && $userMembers->isNotEmpty() ? $userMembers->random()->member_id : null;
            $letterNumber = 'ST-' . date('Y') . '-' . str_pad($i, 4, '0', STR_PAD_LEFT);

            if ($letterCategories->isEmpty()) {
                continue;
            }
            if (Letter::withTrashed()->where('letter_number', $letterNumber)->exists()) {
                continue;
            }

            Letter::create([
                'letter_number' => $letterNumber,
                'subject' => fake()->sentence(6),
                'body' => fake()->paragraphs(4, true),
                'letter_category_id' => $letterCategories->random()->id,
                'urgency' => fake()->randomElement(['biasa', 'segera', 'kilat']),
                'confidentiality' => fake()->randomElement(['biasa', 'terbatas', 'rahasia']),
                'status' => fake()->randomElement(['draft', 'submitted', 'revision', 'approved', 'sent', 'archived', 'rejected']),
                'from_unit_id' => $sender->organization_unit_id,
                'creator_user_id' => $sender->id,
                'to_type' => $toType,
                'to_unit_id' => $toUnitId,
                'to_member_id' => $toMemberId,
                'signer_type' => fake()->randomElement(['ketua', 'sekretaris']),
                'month' => (int) date('n'),
                'year' => (int) date('Y'),
                'sequence' => $i,
                'created_at' => fake()->dateTimeBetween('-3 months', 'now'),
            ]);
        }

        // ==================== STEP 6: Finance Ledgers (250) ====================
        $this->command->info('💰 Creating 250 finance ledgers...');
        $financeCategories = FinanceCategory::all();

        foreach (range(1, 250) as $i) {
            if ($financeCategories->isEmpty()) {
                break;
            }
            $type = fake()->randomElement(['income', 'income', 'expense', 'expense', 'expense']); // More expenses
            $amount = $type === 'income'
                ? fake()->numberBetween(100000, 5000000)
                : fake()->numberBetween(50000, 2000000);

            $status = fake()->randomElement(['draft', 'submitted', 'approved', 'rejected']);
            $approvedBy = $status === 'approved' ? $creator->id : null;
            $approvedAt = $status === 'approved' ? fake()->dateTimeBetween('-1 year', 'now') : null;
            $rejectedReason = $status === 'rejected' ? fake()->sentence(6) : null;

            FinanceLedger::create([
                'type' => $type,
                'amount' => $amount,
                'description' => fake()->sentence(5),
                'date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
                'finance_category_id' => $financeCategories->random()->id,
                'organization_unit_id' => $units->random()->id,
                'status' => $status,
                'approved_by' => $approvedBy,
                'approved_at' => $approvedAt,
                'rejected_reason' => $rejectedReason,
                'submitted_at' => fake()->dateTimeBetween('-1 year', 'now'),
                'created_by' => $creator->id,
                'created_at' => fake()->dateTimeBetween('-1 year', 'now'),
            ]);
        }

        // ==================== STEP 7: Mutations (80) ====================
        $this->command->info('🔄 Creating 80 mutation requests...');
        $activeMembers = Member::where('status', 'aktif')->get();

        foreach (range(1, 80) as $i) {
            if ($activeMembers->count() < 2)
                break;

            $member = $activeMembers->random();
            $fromUnit = $member->organization_unit_id;
            $toUnit = $units->where('id', '!=', $fromUnit)->random()->id;
            $status = fake()->randomElement(['pending', 'approved', 'rejected']);
            $submitterId = $member->user_id ?? $creator->id;
            $approverId = $status === 'approved' ? $creator->id : null;

            MutationRequest::create([
                'member_id' => $member->id,
                'from_unit_id' => $fromUnit,
                'to_unit_id' => $toUnit,
                'reason' => fake()->paragraph(),
                'status' => $status,
                'effective_date' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
                'sla_status' => fake()->randomElement(['normal', 'warning', 'breach']),
                'submitted_by' => $submitterId,
                'approved_by' => $approverId,
                'created_at' => fake()->dateTimeBetween('-2 months', 'now'),
            ]);
        }

        // ==================== STEP 8: Onboarding/Pending Members (50) ====================
        $this->command->info('🆕 Creating 50 pending members (onboarding)...');
        $usersWithoutMember = User::whereNull('member_id')->limit(50)->get();

        foreach (range(1, min(50, $usersWithoutMember->count())) as $i) {
            $status = fake()->randomElement(['pending', 'approved', 'rejected']);
            $reviewerId = $status === 'pending' ? null : $creator->id;

            PendingMember::create([
                'user_id' => $usersWithoutMember[$i - 1]->id ?? null,
                'name' => fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'organization_unit_id' => $units->random()->id,
                'notes' => fake()->sentence(8),
                'status' => $status,
                'reviewer_id' => $reviewerId,
                'created_at' => fake()->dateTimeBetween('-3 months', 'now'),
            ]);
        }

        // ==================== Summary ====================
        $this->command->newLine();
        $this->command->info('✅ Development data seeding completed!');
        $this->command->newLine();
        $this->command->table(
            ['Module', 'Records Created'],
            [
                ['Members', Member::count()],
                ['Users', User::count()],
                ['Announcements', Announcement::count()],
                ['Aspirations', Aspiration::count()],
                ['Letters', Letter::count()],
                ['Finance Ledgers', FinanceLedger::count()],
                ['Mutations', MutationRequest::count()],
                ['Pending Members', PendingMember::count()],
            ]
        );
    }
}
