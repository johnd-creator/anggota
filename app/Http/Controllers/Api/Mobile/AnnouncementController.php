<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\AnnouncementResource;
use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\AnnouncementDismissal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Announcement::class);

        $query = Announcement::query()
            ->visibleTo($request->user())
            ->whereDoesntHave('dismissals', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with(['creator:id,name', 'organizationUnit:id,name,code', 'attachments'])
            ->latest();

        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('body', 'like', "%{$search}%"));
        }

        $items = $query->paginate((int) $request->integer('per_page', 10));

        return response()->json([
            'items' => AnnouncementResource::collection($items->getCollection()),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function show(Request $request, Announcement $announcement): JsonResponse
    {
        Gate::authorize('view', $announcement);

        return response()->json([
            'announcement' => new AnnouncementResource($announcement->load(['creator:id,name', 'organizationUnit:id,name,code', 'attachments'])),
        ]);
    }

    public function dismiss(Request $request, Announcement $announcement): JsonResponse
    {
        Gate::authorize('view', $announcement);

        if (! Schema::hasTable('announcement_dismissals')) {
            return response()->json(['message' => 'Feature requires migration: announcement_dismissals'], 503);
        }

        AnnouncementDismissal::updateOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $request->user()->id],
            ['dismissed_at' => now()]
        );

        return response()->json(['status' => 'ok']);
    }

    public function downloadAttachment(Request $request, AnnouncementAttachment $attachment)
    {
        Gate::authorize('download', $attachment);

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            return response()->json(['message' => 'Lampiran tidak ditemukan.'], 404);
        }

        return response()->download(
            Storage::disk($attachment->disk)->path($attachment->path),
            $attachment->original_name
        );
    }
}
