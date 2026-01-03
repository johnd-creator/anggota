<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Announcement::class);

        $query = Announcement::query()
            ->visibleTo($request->user())
            ->with(['creator', 'organizationUnit', 'attachments'])
            ->latest();

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        return Inertia::render('Announcements/Index', [
            'announcements' => $query->paginate(10)->withQueryString()->through(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'body' => $item->body,
                    'scope_type' => $item->scope_type,
                    'organization_unit_name' => $item->organizationUnit?->name,
                    'is_pinned' => $item->pin_to_dashboard,
                    'created_at' => $item->created_at,
                    'attachments' => $item->attachments->map(fn($f) => [
                        'id' => $f->id,
                        'original_name' => $f->original_name,
                        'size' => $f->size,
                        'download_url' => $f->download_url,
                    ]),
                ];
            }),
            'filters' => $request->only(['q']),
        ]);
    }

    public function create()
    {
        Gate::authorize('create', Announcement::class);
        return Inertia::render('Announcements/Create');
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Announcement::class);
        // TODO: Full validation and logic in Prompt 2
        return redirect()->route('announcements.index');
    }

    public function show(Announcement $announcement)
    {
        Gate::authorize('view', $announcement);
        return Inertia::render('Announcements/Show', [
            'announcement' => $announcement->load('attachments', 'creator')
        ]);
    }

    public function edit(Announcement $announcement)
    {
        Gate::authorize('update', $announcement);
        return Inertia::render('Announcements/Edit');
    }

    public function update(Request $request, Announcement $announcement)
    {
        Gate::authorize('update', $announcement);
        // TODO: Update logic in Prompt 2
        return redirect()->route('announcements.index');
    }

    public function destroy(Announcement $announcement)
    {
        Gate::authorize('delete', $announcement);
        $announcement->delete();
        return redirect()->route('announcements.index');
    }

    public function downloadAttachment(AnnouncementAttachment $attachment)
    {
        Gate::authorize('download', $attachment);

        if (!Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404);
        }

        return response()->download(
            Storage::disk($attachment->disk)->path($attachment->path),
            $attachment->original_name
        );
    }
}
