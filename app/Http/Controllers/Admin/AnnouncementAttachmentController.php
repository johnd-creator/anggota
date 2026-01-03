<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnnouncementAttachmentUploadRequest;
use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Services\AnnouncementAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AnnouncementAttachmentController extends Controller
{
    protected $service;

    public function __construct(AnnouncementAttachmentService $service)
    {
        $this->service = $service;
    }

    public function store(AnnouncementAttachmentUploadRequest $request, Announcement $announcement)
    {
        // User must be able to update the announcement to add files
        Gate::authorize('update', $announcement);

        $files = $request->file('attachments'); // array of UploadedFile

        $this->service->storeFiles($announcement, $files, $request->user());

        return back()->with('success', 'Lampiran berhasil diupload.');
    }

    public function destroy(AnnouncementAttachment $attachment)
    {
        // Check policy - usually checks if user can update the parent announcement
        Gate::authorize('delete', $attachment);

        $this->service->deleteAttachment($attachment);

        return back()->with('success', 'Lampiran berhasil dihapus.');
    }
}
