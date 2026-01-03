<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnouncementAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure roles exist
        if (!Role::where('name', 'admin_unit')->exists())
            Role::create(['name' => 'admin_unit', 'label' => 'Admin Unit']);
    }

    public function test_admin_can_upload_attachments_to_own_announcement()
    {
        Storage::fake('local');

        $unit = OrganizationUnit::factory()->create();
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        $announcement = Announcement::create([
            'title' => 'My Announcement',
            'body' => 'Body',
            'scope_type' => 'unit',
            'organization_unit_id' => $unit->id,
            'created_by' => $user->id,
            'is_active' => true,
        ]);

        $file = UploadedFile::fake()->create('document.pdf', 100); // 100KB

        $response = $this->actingAs($user)->post(route('admin.announcements.attachments.store', $announcement->id), [
            'attachments' => [$file],
        ]);

        $response->assertSessionHas('success');

        // Assert file stored
        $attachment = $announcement->attachments()->first();
        $this->assertNotNull($attachment);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_attachment_is_deleted_physically()
    {
        Storage::fake('local');

        $user = User::factory()->create(['role_id' => Role::where('name', 'admin_unit')->first()->id]);
        $announcement = Announcement::create([
            'title' => 'Delete Test',
            'body' => 'B',
            'scope_type' => 'unit',
            'organization_unit_id' => $user->organization_unit_id,
            'created_by' => $user->id
        ]);

        // Upload first
        $file = UploadedFile::fake()->create('todelete.jpg', 500);
        $this->actingAs($user)->post(route('admin.announcements.attachments.store', $announcement->id), [
            'attachments' => [$file],
        ]);

        $attachment = $announcement->attachments()->first();
        Storage::disk('local')->assertExists($attachment->path);

        // Delete
        $response = $this->actingAs($user)->delete(route('admin.announcements.attachments.destroy', $attachment->id));
        $response->assertSessionHas('success');

        $this->assertModelMissing($attachment);
        Storage::disk('local')->assertMissing($attachment->path);
    }
}
