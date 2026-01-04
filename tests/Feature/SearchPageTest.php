<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Announcement;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_search_results_page()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/search?q=test');

        $response->assertStatus(200)
            ->assertInertia(
                fn($page) => $page
                    ->component('Search/Index')
                    ->has('results')
                    ->has('allowed_types')
                    ->where('query', 'test')
            );
    }

    public function test_results_grouped_by_default()
    {
        $user = User::factory()->create();
        Announcement::create([
            'title' => 'Test Announcement',
            'body' => 'Content of test',
            'scope_type' => 'global_all',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get('/search?q=Test');

        $response->assertStatus(200)
            ->assertInertia(
                fn($page) => $page
                    ->has('results.announcements')
            );
    }

    public function test_can_filter_and_paginate_results()
    {
        $user = User::factory()->create();
        // Create 20 announcements
        for ($i = 0; $i < 20; $i++) {
            Announcement::create([
                'title' => "Announcement $i",
                'body' => 'Body content',
                'scope_type' => 'global_all',
                'is_active' => true,
                'created_by' => $user->id,
            ]);
        }

        // Search specifically for type 'announcements'
        $response = $this->actingAs($user)
            ->get('/search?q=Announcement&type=announcements');

        $response->assertStatus(200)
            ->assertInertia(
                fn($page) => $page
                    ->where('activeType', 'announcements')
                    ->has('results.announcements.data', 15) // Default pagination 15
                    ->has('results.announcements.next_page_url')
            );
    }

    public function test_cannot_search_unauthorized_type()
    {
        $user = User::factory()->create(['role_id' => null]); // Member role

        // Members cannot search 'members' types
        $response = $this->actingAs($user)
            ->get('/search?q=Test&type=members');

        $response->assertStatus(403);
    }
}
