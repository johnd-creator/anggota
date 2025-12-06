<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessMembersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\OrganizationUnitSeeder::class);
    }

    public function test_reguler_cannot_access_admin_members()
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'reguler')->first()->id]);
        $response = $this->actingAs($user)->get('/admin/members');
        $response->assertRedirect(route('itworks'));
    }

    public function test_super_admin_can_access_admin_members()
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'super_admin')->first()->id]);
        $response = $this->actingAs($user)->get('/admin/members');
        $response->assertStatus(200);
    }
}

