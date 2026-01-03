<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiReportsScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_api_growth_requires_unit_id(): void
    {
        config(['app.api_token' => 'test-token']);

        $response = $this->withHeaders(['X-API-Token' => 'test-token'])
            ->get('/api/reports/growth');

        $response->assertStatus(400);
        $response->assertJson(['error' => 'unit_id required']);
    }

    public function test_api_growth_returns_scoped_data(): void
    {
        config(['app.api_token' => 'test-token']);

        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        Member::factory()->count(3)->create(['organization_unit_id' => $unitA->id, 'join_date' => now()]);

        $response = $this->withHeaders(['X-API-Token' => 'test-token'])
            ->get('/api/reports/growth?unit_id=' . $unitA->id);

        $response->assertStatus(200);
        // The series should reflect the count
        // Note: series format is list of objects {label, value}
        // We verify that somewhere in series there is value 3 for current month
        $currentMonth = now()->format('Y-m');
        $series = $response->json('series');

        $found = false;
        foreach ($series as $point) {
            if ($point['label'] === $currentMonth && $point['value'] === 3) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Series should contain count 3 for current month');
    }

    public function test_api_documents_no_pii_leak(): void
    {
        config(['app.api_token' => 'test-token']);

        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        Member::factory()->create([
            'organization_unit_id' => $unitA->id,
            'email' => 'leaky@example.com',
            'phone' => '08123456789'
        ]);

        $response = $this->withHeaders(['X-API-Token' => 'test-token'])
            ->get('/api/reports/documents?unit_id=' . $unitA->id);

        $response->assertStatus(200);
        $items = $response->json('items');
        $item = $items[0];

        $this->assertArrayNotHasKey('email', $item);
        $this->assertArrayNotHasKey('phone', $item);
        $this->assertArrayNotHasKey('documents', $item); // Should just have boolean flag

        $this->assertArrayHasKey('has_documents', $item);
        $this->assertArrayHasKey('has_photo', $item);
        $this->assertEquals($unitA->id, $item['organization_unit_id']);
    }

    public function test_api_mutations_requires_unit_id(): void
    {
        config(['app.api_token' => 'test-token']);

        $response = $this->withHeaders(['X-API-Token' => 'test-token'])
            ->get('/api/reports/mutations');

        $response->assertStatus(400);
    }
}
