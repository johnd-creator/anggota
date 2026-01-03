#!/usr/bin/env php
<?php

/*
 * Standalone script untuk generate development data
 * Run: php generate-dev-data.php
 */

// Bootstrap Laravel
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\{Announcement, Aspiration, AspirationCategory, FinanceCategory, FinanceLedger, Letter, LetterCategory, Member, MutationRequest, OrganizationUnit, PendingMember, Role, UnionPosition, User};

echo "🚀 Starting Development Data Generation...\n\n";

// Step 0: Check prerequisites
echo "📋 Checking prerequisites...\n";
$units = OrganizationUnit::all();
$roles = Role::all();
$positions = UnionPosition::all();

if ($units->count() === 0) {
    die("❌ No organization units found. Run: php artisan db:seed --class=OrganizationUnitSeeder\n");
}
if ($roles->count() === 0) {
    die("❌ No roles found. Run: php artisan db:seed --class=RoleSeeder\n");
}
if ($positions->count() === 0) {
    die("❌ No union positions found. Run: php artisan db:seed --class=UnionPositionSeeder\n");
}

$posAnggota = $positions->where('name', 'Anggota')->first();
$posKetua = $positions->where('name', 'Ketua')->first();
$posSekretaris = $positions->where('name', 'Sekretaris')->first();
$roleAnggota = $roles->where('name', 'anggota')->first();

echo "✓ Prerequisites OK\n\n";

// Step 1: Members (500)
echo "📝 Creating 500 members...\n";
$members = [];
for ($i = 1; $i <= 500; $i++) {
    $unit = $units->random();
    $rand = rand(1, 100);
    $pos = $rand <= 70 ? $posAnggota : ($rand <= 85 ? $posKetua : $posSekretaris);

    $member = Member::create([
        'full_name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'phone' => fake()->phoneNumber(),
        'birth_place' => fake()->city(),
        'birth_date' => fake()->date('Y-m-d', '-25 years'),
        'job_title' => fake()->jobTitle(),
        'employment_type' => fake()->randomElement(['organik', 'tkwt']),
        'status' => 'aktif',
        'join_date' => fake()->dateTimeBetween('-10 years', 'now')->format('Y-m-d'),
        'company_join_date' => fake()->dateTimeBetween('-15 years', 'now')->format('Y-m-d'),
        'organization_unit_id' => $unit->id,
        'union_position_id' => $pos?->id,
        'kta_number' => 'KTA-' . str_pad($i, 6, '0', STR_PAD_LEFT),
        'nra' => 'NRA-' . str_pad($i, 8, '0', STR_PAD_LEFT),
        'nip' => 'NIP-' . fake()->numerify('################'),
        'join_year' => rand(2015, 2025),
        'sequence_number' => $i,
    ]);

    $members[] = $member;

    if ($i % 100 === 0) {
        echo "  ✓ {$i}/500 members created\n";
    }
}
echo "✓ 500 members created!\n\n";

// Step 2: Users (200)
echo "👤 Creating 200 users...\n";
foreach (array_slice($members, 0, 200) as $idx => $member) {
    try {
        $user = User::create([
            'name' => $member->full_name,
            'email' => $member->email,
            'password' => bcrypt('password'),
            'role_id' => $roleAnggota->id,
            'member_id' => $member->id,
            'organization_unit_id' => $member->organization_unit_id,
        ]);

        $member->user_id = $user->id;
        $member->save();

        if (($idx + 1) % 50 === 0) {
            echo "  ✓ " . ($idx + 1) . "/200 users created\n";
        }
    } catch (\Exception $e) {
        echo "  ⚠ Skipped user for {$member->email}: " . $e->getMessage() . "\n";
    }
}
echo "✓ Users created!\n\n";

// Step 3: Announcements (150)
echo "📢 Creating 150 announcements...\n";
$creator = User::first();
if (!$creator) {
    echo "  ⚠ No users found, skipping announcements\n";
} else {
    for ($i = 1; $i <= 150; $i++) {
        $rand = rand(1, 100);
        if ($rand <= 50) {
            $scope = 'global_all';
            $unitId = null;
        } elseif ($rand <= 70) {
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
            'is_active' => true,
            'pin_to_dashboard' => rand(1, 100) <= 20,
            'created_by' => $creator->id,
            'created_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);

        if ($i % 50 === 0) {
            echo "  ✓ {$i}/150 announcements created\n";
        }
    }
    echo "✓ 150 announcements created!\n\n";
}

// Step 4: Aspirations (200)
echo "💡 Creating 200 aspirations...\n";
$categories = AspirationCategory::all();
$userMembers = User::whereNotNull('member_id')->get();

if ($userMembers->count() === 0) {
    echo "  ⚠ No users with members found, skipping aspirations\n";
} else {
    for ($i = 1; $i <= 200; $i++) {
        $user = $userMembers->random();
        $member = Member::find($user->member_id);

        Aspiration::create([
            'title' => fake()->sentence(5),
            'description' => fake()->paragraphs(2, true),
            'category_id' => $categories->random()?->id,
            'user_id' => $user->id,
            'organization_unit_id' => $member->organization_unit_id,
            'status' => fake()->randomElement(['new', 'new', 'reviewed']),
            'created_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ]);

        if ($i % 50 === 0) {
            echo "  ✓ {$i}/200 aspirations created\n";
        }
    }
    echo "✓ 200 aspirations created!\n\n";
}

// Step 5: Letters (100)
echo "✉️ Creating 100 letters...\n";
$letterCats = LetterCategory::all();
$senders = User::whereHas('role', fn($q) => $q->whereIn('name', ['super_admin', 'admin_pusat', 'admin_unit']))->get();

if ($senders->count() === 0) {
    echo "  ⚠ No admin users found, using first user\n";
    $senders = User::limit(1)->get();
}

if ($senders->count() > 0) {
    for ($i = 1; $i <= 100; $i++) {
        $sender = $senders->random();

        Letter::create([
            'number' => 'ST-' . date('Y') . '-' . str_pad($i, 4, '0', STR_PAD_LEFT),
            'subject' => fake()->sentence(6),
            'content' => fake()->paragraphs(4, true),
            'category_id' => $letterCats->random()?->id,
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'status' => fake()->randomElement(['draft', 'submitted']),
            'from_unit_id' => $sender->organization_unit_id ?? $units->first()->id,
            'creator_user_id' => $sender->id,
            'to_type' => 'unit',
            'to_unit_id' => $units->random()->id,
            'signer_type' => 'ketua',
            'created_at' => fake()->dateTimeBetween('-3 months', 'now'),
        ]);

        if ($i % 25 === 0) {
            echo "  ✓ {$i}/100 letters created\n";
        }
    }
    echo "✓ 100 letters created!\n\n";
}

// Step 6: Finance (250)
echo "💰 Creating 250 finance ledgers...\n";
$finCats = FinanceCategory::all();

for ($i = 1; $i <= 250; $i++) {
    $type = rand(1, 100) <= 40 ? 'income' : 'expense';
    $amount = $type === 'income' ? rand(100000, 5000000) : rand(50000, 2000000);

    FinanceLedger::create([
        'type' => $type,
        'amount' => $amount,
        'description' => fake()->sentence(5),
        'date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        'category_id' => $finCats->random()?->id,
        'organization_unit_id' => $units->random()->id,
        'status' => 'approved',
        'created_by' => User::first()->id,
        'created_at' => fake()->dateTimeBetween('-1 year', 'now'),
    ]);

    if ($i % 50 === 0) {
        echo "  ✓ {$i}/250 ledgers created\n";
    }
}
echo "✓ 250 finance ledgers created!\n\n";

// Step 7: Mutations (80)
echo "🔄 Creating 80 mutation requests...\n";
$activeMembers = Member::where('status', 'aktif')->get();

if ($activeMembers->count() < 2) {
    echo "  ⚠ Not enough active members, skipping mutations\n";
} else {
    for ($i = 1; $i <= 80; $i++) {
        $member = $activeMembers->random();
        $fromUnit = $member->organization_unit_id;
        $toUnit = $units->where('id', '!=', $fromUnit)->random()->id;

        MutationRequest::create([
            'member_id' => $member->id,
            'from_unit_id' => $fromUnit,
            'to_unit_id' => $toUnit,
            'reason' => fake()->paragraph(),
            'status' => 'pending',
            'effective_date' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'sla_status' => 'normal',
            'created_at' => fake()->dateTimeBetween('-2 months', 'now'),
        ]);

        if ($i % 20 === 0) {
            echo "  ✓ {$i}/80 mutations created\n";
        }
    }
    echo "✓ 80 mutations created!\n\n";
}

// Step 8: Onboarding (50)
echo "🆕 Creating 50 pending members...\n";
for ($i = 1; $i <= 50; $i++) {
    PendingMember::create([
        'full_name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'phone' => fake()->phoneNumber(),
        'birth_place' => fake()->city(),
        'birth_date' => fake()->date('Y-m-d', '-30 years'),
        'job_title' => fake()->jobTitle(),
        'employment_type' => 'organik',
        'join_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
        'organization_unit_id' => $units->random()->id,
        'status' => 'pending',
        'created_at' => fake()->dateTimeBetween('-3 months', 'now'),
    ]);
}
echo "✓ 50 pending members created!\n\n";

// Summary
echo "\n";
echo "════════════════════════════════════════\n";
echo "    ✅ DEVELOPMENT DATA CREATED!       \n";
echo "════════════════════════════════════════\n\n";

$summary = [
    ['Module', 'Count'],
    ['─────────────────', '──────'],
    ['Members', Member::count()],
    ['Users', User::count()],
    ['Announcements', Announcement::count()],
    ['Aspirations', Aspiration::count()],
    ['Letters', Letter::count()],
    ['Finance Ledgers', FinanceLedger::count()],
    ['Mutations', MutationRequest::count()],
    ['Pending Members', PendingMember::count()],
    ['─────────────────', '──────'],
    ['TOTAL RECORDS', Member::count() + User::count() + Announcement::count() + Aspiration::count() + Letter::count() + FinanceLedger::count() + MutationRequest::count() + PendingMember::count()],
];

foreach ($summary as $row) {
    printf("%-20s %s\n", $row[0], $row[1]);
}

echo "\n🎉 Done! Login with any user email and password: password\n\n";
