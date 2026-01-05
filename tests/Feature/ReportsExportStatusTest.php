<?php

namespace Tests\Feature;

use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\ReportExportStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ReportsExportStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_user_can_get_initial_status(): void
    {
        config(['features.finance' => true]);

        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id
        ]);

        $response = $this->actingAs($user)->getJson(route('reports.export.status'));

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'idle'
            ]);
    }

    public function test_status_updates_lifecycle(): void
    {
        config(['features.finance' => true]);

        $admin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id
        ]);

        // 1. Start export (mocking a long running process via controlled service interaction or just checking controller side effect)
        // Since export is streaming, we can't easily interrupt it in a test to check "started" state unless we mock the exporter service or partial mock.
        // Instead, let's verify the SERVICE logic directly ensures cache is written, and then verify controller completes it.

        $service = new ReportExportStatus();
        $service->start($admin, 'members', ['foo' => 'bar']);

        $status = $service->get($admin);
        $this->assertEquals('started', $status['status']);
        $this->assertEquals('members', $status['type']);

        // 2. Complete
        $service->complete($admin, 'members', 100, [], 'file.csv');
        $status = $service->get($admin);
        $this->assertEquals('completed', $status['status']);
        $this->assertEquals(100, $status['row_count']);
        $this->assertEquals('file.csv', $status['filename']);
    }

    public function test_export_controller_sets_status(): void
    {
        config(['features.finance' => true]);

        $unit = OrganizationUnit::factory()->create();
        $admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id
        ]);

        // Request an export
        $response = $this->actingAs($admin)->get('/reports/export?type=members');
        $response->assertStatus(200);

        // After response stream is initiated (and technically finished in test env), status should be completed
        $service = new ReportExportStatus();
        $status = $service->get($admin);

        // NOTE: In streaming response tests, the completion callback might run after response is sent. 
        // StreamedResponse executes callback when content() is retrieved or sent.
        // Laravel's test client processes stream immediately.

        $this->assertEquals('completed', $status['status']);
        $this->assertEquals('members', $status['type']);
        $this->assertNotNull($status['filename']);
    }

    public function test_unauthorized_user_cannot_check_status(): void
    {
        // Ensure a role exists that doesn't have report permissions.
        // If 'anggota' doesn't exist from seeder, create it.
        $role = Role::where('name', 'anggota')->first();
        if (!$role) {
            $role = Role::create(['name' => 'anggota', 'label' => 'Anggota']);
        }

        $user = User::factory()->create([
            'role_id' => $role->id
        ]);

        $response = $this->actingAs($user)->getJson(route('reports.export.status'));

        $response->assertStatus(403);
    }
}
