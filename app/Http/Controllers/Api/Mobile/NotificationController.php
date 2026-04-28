<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\NotificationResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! Schema::hasTable('notifications')) {
            return response()->json(['items' => [], 'meta' => ['total' => 0]]);
        }

        $items = DatabaseNotification::where('notifiable_type', User::class)
            ->where('notifiable_id', $request->user()->id)
            ->latest()
            ->paginate((int) $request->integer('per_page', 15));

        return response()->json([
            'items' => NotificationResource::collection($items->getCollection()),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = DatabaseNotification::where('notifiable_type', User::class)
            ->where('notifiable_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'status' => 'ok',
            'notification' => new NotificationResource($notification->fresh()),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = DatabaseNotification::where('notifiable_type', User::class)
            ->where('notifiable_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['status' => 'ok', 'count' => $count]);
    }

    public function markUnread(Request $request, string $id): JsonResponse
    {
        $notification = DatabaseNotification::where('notifiable_type', User::class)
            ->where('notifiable_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->forceFill(['read_at' => null])->save();

        return response()->json([
            'status' => 'ok',
            'notification' => new NotificationResource($notification->fresh()),
        ]);
    }

    public function markReadBatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string'],
        ]);

        $count = DatabaseNotification::where('notifiable_type', User::class)
            ->where('notifiable_id', $request->user()->id)
            ->whereIn('id', $data['ids'])
            ->update(['read_at' => now()]);

        return response()->json(['status' => 'ok', 'count' => $count]);
    }

    public function recent(Request $request): JsonResponse
    {
        if (! Schema::hasTable('notifications')) {
            return response()->json(['items' => []]);
        }

        $items = DatabaseNotification::where('notifiable_type', User::class)
            ->where('notifiable_id', $request->user()->id)
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'items' => NotificationResource::collection($items),
        ]);
    }
}
