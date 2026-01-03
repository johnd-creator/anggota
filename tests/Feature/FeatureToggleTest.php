<?php

namespace Tests\Feature;

use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the feature toggle (maintenance switch) functionality.
 *
 * Verifies that routes return 503 when their feature flag is disabled.
 */
class FeatureToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        if (!Role::where('name', 'super_admin')->exists()) {
            Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        }
        if (!Role::where('name', 'admin_unit')->exists()) {
            Role::create(['name' => 'admin_unit', 'label' => 'Admin Unit']);
        }
        if (!Role::where('name', 'anggota')->exists()) {
            Role::create(['name' => 'anggota', 'label' => 'Anggota']);
        }
    }

    /**
     * Test: Public announcements route returns 503 when feature is disabled.
     */
    public function test_public_announcements_returns_503_when_disabled(): void
    {
        // Arrange: Create a regular user
        $role = Role::where('name', 'anggota')->first();
        $user = User::factory()->create(['role_id' => $role->id]);

        // Act: Disable announcements feature and hit the route
        config(['features.announcements' => false]);

        $response = $this->actingAs($user)->get('/announcements');

        // Assert: 503 Service Unavailable
        $response->assertStatus(503);
        $response->assertSee('Fitur sedang maintenance');
    }

    /**
     * Test: Admin announcements route returns 503 when feature is disabled.
     */
    public function test_admin_announcements_returns_503_when_disabled(): void
    {
        // Arrange: Create a super_admin user
        $role = Role::where('name', 'super_admin')->first();
        $user = User::factory()->create(['role_id' => $role->id]);

        // Act: Disable announcements feature and hit the route
        config(['features.announcements' => false]);

        $response = $this->actingAs($user)->get('/admin/announcements');

        // Assert: 503 Service Unavailable
        $response->assertStatus(503);
        $response->assertSee('Fitur sedang maintenance');
    }

    /**
     * Test: Public announcements route works when feature is enabled (default).
     */
    public function test_public_announcements_works_when_enabled(): void
    {
        // Arrange: Create a regular user
        $role = Role::where('name', 'anggota')->first();
        $user = User::factory()->create(['role_id' => $role->id]);

        // Act: Ensure announcements feature is enabled (default)
        config(['features.announcements' => true]);

        $response = $this->actingAs($user)->get('/announcements');

        // Assert: Should succeed (not 503)
        $response->assertStatus(200);
    }

    /**
     * Test: Admin announcements route works for authorized user when feature is enabled.
     */
    public function test_admin_announcements_works_when_enabled(): void
    {
        // Arrange: Create a super_admin user
        $role = Role::where('name', 'super_admin')->first();
        $user = User::factory()->create(['role_id' => $role->id]);

        // Act: Ensure announcements feature is enabled (default)
        config(['features.announcements' => true]);

        $response = $this->actingAs($user)->get('/admin/announcements');

        // Assert: Should succeed (not 503)
        $response->assertStatus(200);
    }
}
