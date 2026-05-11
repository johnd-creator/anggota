<?php

use App\Models\LetterCategory;
use App\Models\Member;
use App\Models\MobileDevice;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use App\Models\FinanceCategory;
use App\Models\FinanceLedger;
use App\Models\DuesPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

function mobileExtendedToken(User $user): string
{
    return $user->createToken('extended-test')->plainTextToken;
}

function mobileExtendedUser(string $roleName, ?OrganizationUnit $unit = null): User
{
    $role = Role::where('name', $roleName)->firstOrFail();

    return User::factory()->create([
        'role_id' => $role->id,
        'organization_unit_id' => $unit?->id,
    ]);
}

test('extended mobile endpoints still require bearer auth', function () {
    $this->getJson('/api/mobile/v1/admin/members')->assertUnauthorized();
    $this->getJson('/api/mobile/v1/letters/inbox')->assertUnauthorized();
    $this->getJson('/api/mobile/v1/finance/ledgers')->assertUnauthorized();
});

test('mobile admin member list is scoped to admin unit', function () {
    $unit = OrganizationUnit::factory()->create();
    $otherUnit = OrganizationUnit::factory()->create();
    $admin = mobileExtendedUser('admin_unit', $unit);
    $ownMember = Member::factory()->create(['organization_unit_id' => $unit->id, 'full_name' => 'Own Unit Member']);
    $otherMember = Member::factory()->create(['organization_unit_id' => $otherUnit->id, 'full_name' => 'Other Unit Member']);

    $this->withHeader('Authorization', 'Bearer '.mobileExtendedToken($admin))
        ->getJson('/api/mobile/v1/admin/members')
        ->assertOk()
        ->assertJsonFragment(['full_name' => $ownMember->full_name])
        ->assertJsonMissing(['full_name' => $otherMember->full_name]);
});

test('mobile letter creator can create draft and read outbox', function () {
    $unit = OrganizationUnit::factory()->create();
    $admin = mobileExtendedUser('admin_unit', $unit);
    $category = LetterCategory::create([
        'name' => 'Undangan',
        'code' => 'UND',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $headers = ['Authorization' => 'Bearer '.mobileExtendedToken($admin)];

    $this->postJson('/api/mobile/v1/letters', [
        'letter_category_id' => $category->id,
        'signer_type' => 'ketua',
        'to_type' => 'external',
        'to_external_name' => 'Mitra Eksternal',
        'subject' => 'Undangan rapat',
        'body' => '<p>Isi surat</p>',
        'confidentiality' => 'normal',
        'urgency' => 'normal',
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('letter.status', 'draft')
        ->assertJsonPath('letter.from_unit.id', $unit->id);

    $this->getJson('/api/mobile/v1/letters/outbox', $headers)
        ->assertOk()
        ->assertJsonFragment(['subject' => 'Undangan rapat']);
});

test('mobile devices can be registered and removed by owner only', function () {
    $user = mobileExtendedUser('anggota');
    $other = mobileExtendedUser('anggota');
    $headers = ['Authorization' => 'Bearer '.mobileExtendedToken($user)];

    $deviceId = $this->postJson('/api/mobile/v1/devices', [
        'platform' => 'android',
        'device_token' => 'fcm-test-token',
        'device_name' => 'Pixel Test',
        'app_version' => '1.0.0',
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('device.user_id', $user->id)
        ->json('device.id');

    $this->app['auth']->forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.mobileExtendedToken($other))
        ->deleteJson('/api/mobile/v1/devices/'.$deviceId)
        ->assertNotFound();

    $this->app['auth']->forgetGuards();
    $this->deleteJson('/api/mobile/v1/devices/'.$deviceId, [], $headers)->assertOk();
    expect(MobileDevice::count())->toBe(0);
});

test('mobile microsoft token exchange endpoint remains an explicit safe stub', function () {
    $this->postJson('/api/mobile/v1/auth/microsoft/token', ['id_token' => 'dummy'])
        ->assertStatus(501)
        ->assertJsonPath('provider', 'microsoft');
});

test('mobile finance dashboard returns summary for bendahara', function () {
    $unit = OrganizationUnit::factory()->create(['name' => 'Unit A', 'is_pusat' => false]);
    $pusat = OrganizationUnit::factory()->create(['name' => 'DPP Pusat', 'is_pusat' => true]);
    $bendahara = mobileExtendedUser('bendahara', $unit);

    $category = FinanceCategory::create(['name' => 'Iuran', 'type' => 'income', 'created_by' => $bendahara->id]);

    FinanceLedger::create([
        'organization_unit_id' => $unit->id,
        'finance_category_id' => $category->id,
        'type' => 'income',
        'amount' => 100000,
        'date' => now(),
        'status' => 'approved',
        'created_by' => $bendahara->id,
    ]);
    FinanceLedger::create([
        'organization_unit_id' => $pusat->id,
        'finance_category_id' => $category->id,
        'type' => 'expense',
        'amount' => 50000,
        'date' => now(),
        'status' => 'approved',
        'created_by' => $bendahara->id,
    ]);
    $otherUnit = OrganizationUnit::factory()->create(['is_pusat' => false]);
    FinanceLedger::create([
        'organization_unit_id' => $otherUnit->id,
        'finance_category_id' => $category->id,
        'type' => 'income',
        'amount' => 999999,
        'date' => now(),
        'status' => 'approved',
        'created_by' => $bendahara->id,
    ]);

    $this->withHeader('Authorization', 'Bearer '.mobileExtendedToken($bendahara))
        ->getJson('/api/mobile/v1/finance/dashboard')
        ->assertOk()
        ->assertJsonPath('summary.balance', function ($balance) {
            return $balance < 1000000;
        });
});

test('mobile finance units returns own_and_pusat for bendahara', function () {
    $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A', 'is_pusat' => false]);
    $pusat = OrganizationUnit::factory()->create(['name' => 'DPP Pusat', 'is_pusat' => true]);
    $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B', 'is_pusat' => false]);
    $bendahara = mobileExtendedUser('bendahara', $unitA);

    $this->withHeader('Authorization', 'Bearer '.mobileExtendedToken($bendahara))
        ->getJson('/api/mobile/v1/finance/units')
        ->assertOk()
        ->assertJsonPath('accessible_count', 2)
        ->assertJsonPath('units.0.name', 'DPP Pusat')
        ->assertJsonPath('units.1.name', 'Unit A');
});

test('mobile finance units returns all for bendahara_pusat', function () {
    OrganizationUnit::factory()->create(['name' => 'Unit X', 'is_pusat' => false]);
    OrganizationUnit::factory()->create(['name' => 'Unit Y', 'is_pusat' => false]);
    OrganizationUnit::factory()->create(['name' => 'DPP Pusat', 'is_pusat' => true]);
    $bendaharaPusat = mobileExtendedUser('bendahara_pusat');

    $this->withHeader('Authorization', 'Bearer '.mobileExtendedToken($bendaharaPusat))
        ->getJson('/api/mobile/v1/finance/units')
        ->assertOk()
        ->assertJsonPath('accessible_count', 3);
});

test('mobile bendahara_pusat can read all ledgers and manage pusat finance only', function () {
    $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A', 'is_pusat' => false]);
    $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B', 'is_pusat' => false]);
    $pusat = OrganizationUnit::factory()->create(['name' => 'DPP Pusat', 'is_pusat' => true]);
    $bendaharaPusat = mobileExtendedUser('bendahara_pusat', $pusat);
    $category = FinanceCategory::create(['name' => 'Iuran', 'type' => 'income', 'created_by' => $bendaharaPusat->id]);

    $ledgerA = FinanceLedger::create([
        'organization_unit_id' => $unitA->id,
        'finance_category_id' => $category->id,
        'type' => 'income',
        'amount' => 100000,
        'date' => now(),
        'status' => 'submitted',
        'created_by' => $bendaharaPusat->id,
    ]);
    FinanceLedger::create([
        'organization_unit_id' => $unitB->id,
        'finance_category_id' => $category->id,
        'type' => 'expense',
        'amount' => 50000,
        'date' => now(),
        'status' => 'submitted',
        'created_by' => $bendaharaPusat->id,
    ]);

    $headers = ['Authorization' => 'Bearer '.mobileExtendedToken($bendaharaPusat)];

    $this->getJson('/api/mobile/v1/finance/ledgers', $headers)
        ->assertOk()
        ->assertJsonCount(2, 'ledgers');

    $nonPusatPayload = [
        'organization_unit_id' => $unitA->id,
        'finance_category_id' => $category->id,
        'type' => 'income',
        'amount' => 120000,
        'date' => now()->toDateString(),
        'description' => 'Should be forbidden',
    ];

    $this->postJson('/api/mobile/v1/finance/ledgers', $nonPusatPayload, $headers)->assertForbidden();
    $this->putJson('/api/mobile/v1/finance/ledgers/'.$ledgerA->id, $nonPusatPayload, $headers)->assertForbidden();
    $this->deleteJson('/api/mobile/v1/finance/ledgers/'.$ledgerA->id, [], $headers)->assertForbidden();
    $this->postJson('/api/mobile/v1/finance/ledgers/'.$ledgerA->id.'/approve', [], $headers)->assertForbidden();

    $pusatCategoryId = $this->postJson('/api/mobile/v1/finance/categories', [
        'name' => 'Pusat category',
        'type' => 'income',
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('category.organization_unit_id', $pusat->id)
        ->json('category.id');

    $pusatLedgerId = $this->postJson('/api/mobile/v1/finance/ledgers', [
        'organization_unit_id' => $pusat->id,
        'finance_category_id' => $pusatCategoryId,
        'type' => 'income',
        'amount' => 120000,
        'date' => now()->toDateString(),
        'description' => 'Pusat income',
    ], $headers)
        ->assertCreated()
        ->json('ledger.id');

    $this->putJson('/api/mobile/v1/finance/ledgers/'.$pusatLedgerId, [
        'organization_unit_id' => $pusat->id,
        'finance_category_id' => $pusatCategoryId,
        'type' => 'income',
        'amount' => 125000,
        'date' => now()->toDateString(),
        'description' => 'Updated pusat income',
    ], $headers)->assertOk();

    $this->deleteJson('/api/mobile/v1/finance/ledgers/'.$pusatLedgerId, [], $headers)->assertOk();
});

test('mobile bendahara can read pusat ledgers and dues but cannot write pusat data', function () {
    $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A', 'is_pusat' => false]);
    $pusat = OrganizationUnit::factory()->create(['name' => 'DPP Pusat', 'is_pusat' => true]);
    $bendahara = mobileExtendedUser('bendahara', $unitA);
    $category = FinanceCategory::create(['name' => 'Iuran', 'type' => 'income', 'created_by' => $bendahara->id]);
    $ledgerPusat = FinanceLedger::create([
        'organization_unit_id' => $pusat->id,
        'finance_category_id' => $category->id,
        'type' => 'income',
        'amount' => 100000,
        'date' => now(),
        'status' => 'draft',
        'created_by' => $bendahara->id,
    ]);
    $memberPusat = Member::factory()->create(['organization_unit_id' => $pusat->id]);
    $duesPusat = DuesPayment::create([
        'member_id' => $memberPusat->id,
        'organization_unit_id' => $pusat->id,
        'period' => now()->format('Y-m'),
        'status' => 'paid',
        'amount' => 100000,
        'paid_at' => now(),
        'recorded_by' => $bendahara->id,
    ]);

    $headers = ['Authorization' => 'Bearer '.mobileExtendedToken($bendahara)];

    $this->getJson('/api/mobile/v1/finance/ledgers?unit_id='.$pusat->id, $headers)
        ->assertOk()
        ->assertJsonFragment(['id' => $ledgerPusat->id]);
    $this->getJson('/api/mobile/v1/finance/dues?unit_id='.$pusat->id, $headers)
        ->assertOk()
        ->assertJsonFragment(['period' => $duesPusat->period]);

    $this->putJson('/api/mobile/v1/finance/ledgers/'.$ledgerPusat->id, [
        'organization_unit_id' => $pusat->id,
        'finance_category_id' => $category->id,
        'type' => 'income',
        'amount' => 120000,
        'date' => now()->toDateString(),
    ], $headers)->assertForbidden();
    $this->patchJson('/api/mobile/v1/finance/dues/'.$duesPusat->id, [
        'status' => 'paid',
        'amount' => 120000,
        'paid_at' => now()->toDateString(),
    ], $headers)->assertForbidden();
});

test('mobile finance dues mirrors web member checklist list', function () {
    $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A', 'is_pusat' => false]);
    $bendahara = mobileExtendedUser('bendahara', $unitA);
    $period = now()->format('Y-m');
    $paidMember = Member::factory()->create([
        'organization_unit_id' => $unitA->id,
        'full_name' => 'Anggota Sudah Bayar',
    ]);
    $unpaidMember = Member::factory()->create([
        'organization_unit_id' => $unitA->id,
        'full_name' => 'Anggota Belum Bayar',
    ]);

    DuesPayment::create([
        'member_id' => $paidMember->id,
        'organization_unit_id' => $unitA->id,
        'period' => $period,
        'status' => 'paid',
        'amount' => 30000,
        'paid_at' => now(),
        'recorded_by' => $bendahara->id,
    ]);

    $headers = ['Authorization' => 'Bearer '.mobileExtendedToken($bendahara)];

    $this->getJson('/api/mobile/v1/finance/dues?period='.$period, $headers)
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonFragment([
            'member_id' => $paidMember->id,
            'member_name' => 'Anggota Sudah Bayar',
            'status' => 'paid',
        ])
        ->assertJsonFragment([
            'member_id' => $unpaidMember->id,
            'member_name' => 'Anggota Belum Bayar',
            'status' => 'unpaid',
        ]);

    $this->getJson('/api/mobile/v1/finance/dues?period='.$period.'&q=Belum', $headers)
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonFragment(['member_name' => 'Anggota Belum Bayar'])
        ->assertJsonMissing(['member_name' => 'Anggota Sudah Bayar']);

    $this->getJson('/api/mobile/v1/finance/dues/dashboard?period='.$period, $headers)
        ->assertOk()
        ->assertJsonPath('summary.paid', 1)
        ->assertJsonPath('summary.unpaid', 1);
});

test('activity log is created on finance ledger creation', function () {
    $unit = OrganizationUnit::factory()->create();
    $superAdmin = mobileExtendedUser('super_admin', $unit);
    $category = FinanceCategory::create(['name' => 'Iuran', 'type' => 'income', 'created_by' => $superAdmin->id]);

    $this->actingAs($superAdmin);
    $ledger = FinanceLedger::create([
        'organization_unit_id' => $unit->id,
        'finance_category_id' => $category->id,
        'type' => 'income',
        'amount' => 50000,
        'date' => now(),
        'status' => 'approved',
        'created_by' => $superAdmin->id,
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => FinanceLedger::class,
        'subject_id' => $ledger->id,
        'action' => 'finance_ledger_created',
    ]);
});
