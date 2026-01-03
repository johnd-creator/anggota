# Development Data Seeder - Quick Guide

## Tinker Script (Copy-Paste Langsung)

Buka terminal dan jalankan:

```bash
php artisan tinker
```

Lalu paste script berikut:

```php
// ============================================
// COMPREHENSIVE DEVELOPMENT DATA GENERATOR
// Total: 1000+ records across all modules
// ============================================

use App\Models\{Announcement, Aspiration, AspirationCategory, FinanceCategory, FinanceLedger, Letter, LetterCategory, Member, MutationRequest, OrganizationUnit, PendingMember, Role, UnionPosition, User};

echo "🚀 Starting data generation...\n\n";

// Get reference data
$units = OrganizationUnit::all();
$roles = Role::all();
$positions = UnionPosition::all();
$posAnggota = $positions->where('name', 'Anggota')->first();
$posKetua = $positions->where('name', 'Ketua')->first();
$roleAnggota = $roles->where('name', 'anggota')->first();

// 1. Members (500)
echo "📝 Creating 500 members...\n";
$members = [];
foreach (range(1, 500) as $i) {
    $unit = $units->random();
    $pos = rand(1,100) <= 70 ? $posAnggota : $posKetua;
    
    $member = Member::create([
        'full_name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'phone' => fake()->phoneNumber(),
        'birth_place' => fake()->city(),
        'birth_date' => fake()->date('Y-m-d', '-25 years'),
        'job_title' => fake()->jobTitle(),
        'employment_type' => fake()->randomElement(['organik', 'tkwt']),
        'status' => 'aktif',
        'join_date' => fake()->dateTimeBetween('-10 years')->format('Y-m-d'),
        'company_join_date' => fake()->dateTimeBetween('-15 years')->format('Y-m-d'),
        'organization_unit_id' => $unit->id,
        'union_position_id' => $pos?->id,
        'kta_number' => 'KTA-' . str_pad($i, 6, '0', STR_PAD_LEFT),
        'nra' => 'NRA-' . str_pad($i, 8, '0', STR_PAD_LEFT),
        'nip' => 'NIP-' . fake()->numerify('################'),
        'join_year' => rand(2015, 2025),
        'sequence_number' => $i,
    ]);
    
    $members[] = $member;
    if ($i % 50 === 0) echo "  ✓ {$i} members\n";
}

// 2. Users (200)
echo "👤 Creating 200 users...\n";
foreach (array_slice($members, 0, 200) as $member) {
    $user = User::create([
        'name' => $member->full_name,
        'email' => $member->email,
        'password' => bcrypt('password'),
        'role_id' => $roleAnggota->id,
        'member_id' => $member->id,
        'organization_unit_id' => $member->organization_unit_id,
    ]);
    $member->update(['user_id' => $user->id]);
}

// 3. Announcements (150)
echo "📢 Creating 150 announcements...\n";
$creator = User::first();
foreach (range(1, 150) as $i) {
    $rand = rand(1, 100);
    $scope = $rand <= 50 ? 'global_all' : ($rand <= 70 ? 'global_officers' : 'unit');
    $unitId = $scope === 'unit' ? $units->random()->id : null;
    
    Announcement::create([
        'title' => fake()->sentence(6),
        'body' => fake()->paragraphs(3, true),
        'scope_type' => $scope,
        'organization_unit_id' => $unitId,
        'is_active' => true,
        'pin_to_dashboard' => rand(1,100) <= 20,
        'created_by' => $creator->id,
        'created_at' => fake()->dateTimeBetween('-1 year'),
    ]);
    if ($i % 50 === 0) echo "  ✓ {$i} announcements\n";
}

// 4. Aspirations (200)
echo "💡 Creating 200 aspirations...\n";
$categories = AspirationCategory::all();
$userMembers = User::whereNotNull('member_id')->get();
foreach (range(1, 200) as $i) {
    if ($userMembers->isEmpty()) break;
    $user = $userMembers->random();
    $member = Member::find($user->member_id);
    
    Aspiration::create([
        'title' => fake()->sentence(5),
        'description' => fake()->paragraphs(2, true),
        'category_id' => $categories->random()?->id,
        'user_id' => $user->id,
        'organization_unit_id' => $member->organization_unit_id,
        'status' => fake()->randomElement(['new', 'reviewed']),
        'created_at' => fake()->dateTimeBetween('-6 months'),
    ]);
    if ($i % 50 === 0) echo "  ✓ {$i} aspirations\n";
}

// 5. Letters (100)
echo "✉️ Creating 100 letters...\n";
$letterCats = LetterCategory::all();
$senders = User::whereHas('role', fn($q) => $q->whereIn('name', ['admin_pusat','admin_unit']))->get();
foreach (range(1, 100) as $i) {
    if ($senders->isEmpty()) break;
    $sender = $senders->random();
    
    Letter::create([
        'number' => 'ST-'. date('Y') .'-'. str_pad($i, 4, '0', STR_PAD_LEFT),
        'subject' => fake()->sentence(6),
        'content' => fake()->paragraphs(4, true),
        'category_id' => $letterCats->random()?->id,
        'urgency' => 'biasa',
        'confidentiality' => 'biasa',
        'status' => fake()->randomElement(['draft', 'submitted']),
        'from_unit_id' => $sender->organization_unit_id,
        'creator_user_id' => $sender->id,
        'to_type' => 'unit',
        'to_unit_id' => $units->random()->id,
        'signer_type' => 'ketua',
        'created_at' => fake()->dateTimeBetween('-3 months'),
    ]);
    if ($i % 25 === 0) echo "  ✓ {$i} letters\n";
}

// 6. Finance (250)
echo "💰 Creating 250 finance ledgers...\n";
$finCats = FinanceCategory::all();
foreach (range(1, 250) as $i) {
    $type = rand(1,100) <= 40 ? 'income' : 'expense';
    $amount = $type === 'income' ? rand(100000, 5000000) : rand(50000, 2000000);
    
    FinanceLedger::create([
        'type' => $type,
        'amount' => $amount,
        'description' => fake()->sentence(5),
        'date' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
        'category_id' => $finCats->random()?->id,
        'organization_unit_id' => $units->random()->id,
        'status' => 'approved',
        'created_by' => $creator->id,
        'created_at' => fake()->dateTimeBetween('-1 year'),
    ]);
    if ($i % 50 === 0) echo "  ✓ {$i} ledgers\n";
}

// 7. Mutations (80)
echo "🔄 Creating 80 mutations...\n";
$activeMembers = Member::where('status', 'aktif')->get();
foreach (range(1, 80) as $i) {
    if ($activeMembers->count() < 2) break;
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
        'created_at' => fake()->dateTimeBetween('-2 months'),
    ]);
    if ($i % 20 === 0) echo "  ✓ {$i} mutations\n";
}

// 8. Onboarding (50)
echo "🆕 Creating 50 pending members...\n";
foreach (range(1, 50) as $i) {
    PendingMember::create([
        'full_name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'phone' => fake()->phoneNumber(),
        'birth_place' => fake()->city(),
        'birth_date' => fake()->date('Y-m-d', '-30 years'),
        'job_title' => fake()->jobTitle(),
        'employment_type' => 'organik',
        'join_date' => fake()->dateTimeBetween('-6 months')->format('Y-m-d'),
        'organization_unit_id' => $units->random()->id,
        'status' => 'pending',
        'created_at' => fake()->dateTimeBetween('-3 months'),
    ]);
}

// Summary
echo "\n✅ DONE! Summary:\n";
echo "  Members: " . Member::count() . "\n";
echo "  Users: " . User::count() . "\n";
echo "  Announcements: " . Announcement::count() . "\n";
echo "  Aspirations: " . Aspiration::count() . "\n";
echo "  Letters: " . Letter::count() . "\n";
echo "  Finance: " . FinanceLedger::count() . "\n";
echo "  Mutations: " . MutationRequest::count() . "\n";
echo "  Onboarding: " . PendingMember::count() . "\n";
echo "\n🎉 Total: 1000+ records created!\n";
```

## Atau Gunakan Seeder

```bash
# Jalankan seeder
php artisan db:seed --class=DevelopmentDataSeeder
```

## Login Info

Semua user yang dibuat:
- **Email**: Sesuai dengan member email
- **Password**: `password`

Default admin dari UserSeeder:
- **Email**: `admin@example.com`
- **Password**: `password`
