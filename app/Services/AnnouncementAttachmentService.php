<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AnnouncementAttachmentService
{
    /**
     * Store multiple files for an announcement.
     * 
     * @param Announcement $announcement
     * @param array $files - array of UploadedFile objects
     * @param User $uploader
     * @return Collection - Collection of created AnnouncementAttachment models
     */
    public function storeFiles(Announcement $announcement, array $files, User $uploader): Collection
    {
        $createdAttachments = collect();

        foreach ($files as $file) {
            if (!($file instanceof UploadedFile)) {
                continue;
            }

            $attachment = $this->storeSingleFile($announcement, $file, $uploader);
            $createdAttachments->push($attachment);
        }

        return $createdAttachments;
    }

    /**
     * Store a single file.
     */
    protected function storeSingleFile(Announcement $announcement, UploadedFile $file, User $uploader): AnnouncementAttachment
    {
        $disk = 'local'; // Private disk
        $uuid = (string) Str::uuid();
        $extension = $file->getClientOriginalExtension();
        $filename = "{$uuid}.{$extension}";

        // Path structure: announcements/{announcement_id}/{filename}
        $path = "announcements/{$announcement->id}";

        // Store physical file
        $storedPath = $file->storeAs($path, $filename, ['disk' => $disk]);

        // Create DB record
        return AnnouncementAttachment::create([
            'announcement_id' => $announcement->id,
            'disk' => $disk,
            'path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(), // bytes
            'uploaded_by' => $uploader->id,
        ]);
    }

    /**
     * Delete an attachment file and record.
     */
    public function deleteAttachment(AnnouncementAttachment $attachment): void
    {
        // Delete physical file
        if (Storage::disk($attachment->disk)->exists($attachment->path)) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }

        // Delete record
        $attachment->delete();
    }
}
