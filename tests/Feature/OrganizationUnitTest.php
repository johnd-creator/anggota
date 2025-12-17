<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\OrganizationUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganizationUnitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_super_admin_can_view_units()
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'super_admin')->first()->id]);
        OrganizationUnit::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.units.index'));
        $response->assertStatus(200);
    }

    public function test_admin_unit_can_view_units()
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'admin_unit')->first()->id]);
        OrganizationUnit::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.units.index'));
        $response->assertStatus(200);
    }

    public function test_reguler_cannot_view_units()
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'reguler')->first()->id]);

        $response = $this->actingAs($user)->get(route('admin.units.index'));
        // Should be redirected to itworks by middleware or 403 by policy if middleware passed (but middleware catches first)
        $response->assertRedirect(route('itworks'));
    }

    public function test_super_admin_can_create_unit()
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'super_admin')->first()->id]);

        $response = $this->actingAs($user)->post(route('admin.units.store'), [
            'code' => '123',
            'name' => 'Test Unit',
            'organization_type' => 'DPD',
            'abbreviation' => 'TST',
            'address' => 'Test Address 123',
        ]);

        $response->assertRedirect(route('admin.units.index'));
        $this->assertDatabaseHas('organization_units', ['code' => '123']);
    }

    public function test_admin_unit_cannot_create_unit()
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'admin_unit')->first()->id]);

        $response = $this->actingAs($user)->post(route('admin.units.store'), [
            'code' => '123',
            'name' => 'Test Unit',
            'address' => 'Test Address 123',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test that migration adds letterhead columns to organization_units table.
     */
    public function test_migration_adds_letterhead_columns()
    {
        // The migration should have run during RefreshDatabase
        $this->assertTrue(Schema::hasColumn('organization_units', 'letterhead_name'));
        $this->assertTrue(Schema::hasColumn('organization_units', 'letterhead_address'));
        $this->assertTrue(Schema::hasColumn('organization_units', 'letterhead_city'));
        $this->assertTrue(Schema::hasColumn('organization_units', 'letterhead_postal_code'));
        $this->assertTrue(Schema::hasColumn('organization_units', 'letterhead_phone'));
        $this->assertTrue(Schema::hasColumn('organization_units', 'letterhead_email'));
        $this->assertTrue(Schema::hasColumn('organization_units', 'letterhead_website'));
        $this->assertTrue(Schema::hasColumn('organization_units', 'letterhead_fax'));
        $this->assertTrue(Schema::hasColumn('organization_units', 'letterhead_whatsapp'));
        $this->assertTrue(Schema::hasColumn('organization_units', 'letterhead_footer_text'));
        $this->assertTrue(Schema::hasColumn('organization_units', 'letterhead_logo_path'));
    }

    /**
     * Test that seeder creates/maintains unit with code=PST.
     */
    public function test_seeder_creates_pusat_unit()
    {
        // Run the seeder
        $this->seed(\Database\Seeders\OrganizationUnitSeeder::class);

        // Assert that Pusat unit exists
        $this->assertDatabaseHas('organization_units', [
            'code' => 'PST',
            'name' => 'Pusat',
        ]);

        // Verify letterhead_name is set
        $pusat = OrganizationUnit::where('code', 'PST')->first();
        $this->assertNotNull($pusat);
        $this->assertEquals('Serikat Pekerja PT PLN Indonesia Power Services', $pusat->letterhead_name);
    }

    /**
     * Test that seeder is idempotent (can run multiple times).
     */
    public function test_seeder_is_idempotent()
    {
        // Run seeder twice
        $this->seed(\Database\Seeders\OrganizationUnitSeeder::class);
        $this->seed(\Database\Seeders\OrganizationUnitSeeder::class);

        // Should still have only one Pusat unit
        $count = OrganizationUnit::where('code', 'PST')->count();
        $this->assertEquals(1, $count);
    }

    /**
     * Test that unit can be created with alphanumeric code.
     */
    public function test_super_admin_can_create_unit_with_alphanumeric_code()
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'super_admin')->first()->id]);

        $response = $this->actingAs($user)->post(route('admin.units.store'), [
            'code' => 'ABC',
            'name' => 'Test Alphanumeric Unit',
            'organization_type' => 'DPD',
            'abbreviation' => 'ABC',
            'address' => 'Test Address 123',
        ]);

        $response->assertRedirect(route('admin.units.index'));
        $this->assertDatabaseHas('organization_units', ['code' => 'ABC']);
    }

    /**
     * Test that unit can be created with letterhead fields.
     */
    public function test_super_admin_can_create_unit_with_letterhead_fields()
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'super_admin')->first()->id]);

        $response = $this->actingAs($user)->post(route('admin.units.store'), [
            'code' => 'LH1',
            'name' => 'Letterhead Test Unit',
            'organization_type' => 'DPD',
            'abbreviation' => 'LH1',
            'address' => 'Test Address 123',
            'letterhead_name' => 'My Org Letterhead',
            'letterhead_email' => 'test@example.com',
            'letterhead_phone' => '08123456789',
        ]);

        $response->assertRedirect(route('admin.units.index'));
        $this->assertDatabaseHas('organization_units', [
            'code' => 'LH1',
            'letterhead_name' => 'My Org Letterhead',
            'letterhead_email' => 'test@example.com',
        ]);
    }
}
