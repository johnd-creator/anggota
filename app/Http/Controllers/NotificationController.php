<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = DatabaseNotification::query()
            ->where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $user->id);

        if ($cat = $request->query('category')) {
            $query->where('data->category', $cat);
        }
        if ($search = $request->query('search')) {
            $query->where(function($q) use ($search){
                $q->where('data->message', 'like', '%' . $search . '%');
            });
        }
        $dateStart = $request->query('date_start');
        $dateEnd = $request->query('date_end');
        if ($dateStart && $dateEnd) {
            $query->whereBetween('created_at', [$dateStart.' 00:00:00', $dateEnd.' 23:59:59']);
        } elseif ($dateStart) {
            $query->whereDate('created_at', '>=', $dateStart);
        } elseif ($dateEnd) {
            $query->whereDate('created_at', '<=', $dateEnd);
        }

        $items = Schema::hasTable('notifications') ? $query->latest()->paginate(10)->withQueryString() : new LengthAwarePaginator([], 0, 10);
        return Inertia::render('Notifications/Index', [
            'items' => $items,
            'notifications' => $items->items(),
            'filters' => $request->only(['category','search','date_start','date_end']),
        ]);
    }

    public function markRead(string $id)
    {
        $n = DatabaseNotification::find($id);
        if ($n && $n->notifiable_type === \App\Models\User::class && $n->notifiable_id === Auth::id()) {
            $n->read_at = now();
            $n->save();
        }
        return back();
    }

    public function markAllRead()
    {
        if (!Schema::hasTable('notifications')) return back();
        DatabaseNotification::where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        return back();
    }

    public function markUnread(string $id)
    {
        if (!Schema::hasTable('notifications')) return back();
        $n = DatabaseNotification::find($id);
        if ($n && $n->notifiable_type === \App\Models\User::class && $n->notifiable_id === Auth::id()) {
            $n->read_at = null;
            $n->save();
        }
        return back();
    }

    public function recent()
    {
        if (!Schema::hasTable('notifications')) return response()->json(['items' => []]);
        $items = DatabaseNotification::where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', Auth::id())
            ->latest()->limit(5)->get()
            ->map(function ($n) {
                $data = is_array($n->data) ? $n->data : (array) $n->data;
                return [
                    'id' => $n->id,
                    'message' => $data['message'] ?? ($data['title'] ?? ''),
                    'category' => $data['category'] ?? ($n->type ?? 'general'),
                    'link' => $data['link'] ?? ($data['cta_url'] ?? null),
                    'read_at' => $n->read_at,
                    'created_at' => $n->created_at,
                ];
            });
        return response()->json(['items' => $items]);
    }
}
