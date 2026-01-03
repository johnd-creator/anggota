<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aspiration;
use App\Models\AspirationCategory;
use App\Models\AspirationSupport;
use App\Models\AspirationUpdate;
use App\Models\OrganizationUnit;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class AspirationController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAnyAdmin', Aspiration::class);

        $user = $request->user();
        $isGlobal = $user->hasGlobalAccess();
        $unitId = $user->currentUnitId();

        $query = Aspiration::with(['category', 'member:id,full_name', 'unit:id,name']);

        // Unit filtering: non-global users always scoped to their unit
        if (!$isGlobal) {
            if (!$unitId) {
                // No unit = empty result
                $query->whereRaw('1=0');
            } else {
                $query->byUnit($unitId);
            }
        } elseif ($unitIdParam = $request->get('unit_id')) {
            // Global users can filter by unit_id param
            $query->byUnit($unitIdParam);
        }

        // Filters
        if ($categoryId = $request->get('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($status = $request->get('status')) {
            $query->byStatus($status);
        }

        $showMerged = $request->boolean('show_merged', false);
        if (!$showMerged) {
            $query->notMerged();
        }

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sort = $request->get('sort', 'latest');
        if ($sort === 'popular') {
            $query->orderByDesc('support_count');
        } elseif ($sort === 'oldest') {
            $query->oldest();
        } else {
            $query->latest();
        }

        $aspirations = $query->paginate(15)->withQueryString();

        // Stats - scoped to user's unit for non-global
        $statsUnitId = $isGlobal ? null : $unitId;

        return Inertia::render('Admin/Aspirations/Index', [
            'aspirations' => $aspirations,
            'categories' => AspirationCategory::orderBy('name')->get(['id', 'name']),
            'units' => $isGlobal ? OrganizationUnit::orderBy('name')->get(['id', 'name']) : [],
            'filters' => $request->only(['category_id', 'status', 'unit_id', 'search', 'sort', 'show_merged']),
            'stats' => [
                'new' => Aspiration::notMerged()->byStatus('new')->when($statsUnitId, fn($q) => $q->byUnit($statsUnitId))->count(),
                'in_progress' => Aspiration::notMerged()->byStatus('in_progress')->when($statsUnitId, fn($q) => $q->byUnit($statsUnitId))->count(),
                'resolved' => Aspiration::notMerged()->byStatus('resolved')->when($statsUnitId, fn($q) => $q->byUnit($statsUnitId))->count(),
            ],
        ]);
    }

    public function show(Request $request, Aspiration $aspiration)
    {
        Gate::authorize('view', $aspiration);

        $aspiration->load([
            'category',
            'member:id,full_name,email',
            'unit:id,name',
            'tags',
            'updates.user:id,name',
            'mergedFrom.member:id,full_name',
            'mergedInto.member:id,full_name',
        ]);

        // Load supporters with pagination
        $supporters = $aspiration->supporters()
            ->select('members.id', 'members.full_name')
            ->paginate(20);

        // Get merge candidates (same unit, not merged, not self)
        $mergeCandidates = Aspiration::query()
            ->byUnit($aspiration->organization_unit_id)
            ->notMerged()
            ->where('id', '!=', $aspiration->id)
            ->select('id', 'title', 'support_count')
            ->orderByDesc('support_count')
            ->limit(20)
            ->get();

        return Inertia::render('Admin/Aspirations/Show', [
            'aspiration' => $aspiration,
            'supporters' => $supporters,
            'mergeCandidates' => $mergeCandidates,
        ]);
    }

    public function updateStatus(Request $request, Aspiration $aspiration)
    {
        Gate::authorize('update', $aspiration);

        $validated = $request->validate([
            'status' => 'required|in:new,in_progress,resolved',
            'notes' => 'nullable|string|max:1000',
        ]);

        $oldStatus = $aspiration->status;
        $newStatus = $validated['status'];

        if ($oldStatus === $newStatus) {
            return back()->with('info', 'Status tidak berubah');
        }

        DB::transaction(function () use ($aspiration, $oldStatus, $newStatus, $validated, $request) {
            $aspiration->status = $newStatus;
            $aspiration->save();

            AspirationUpdate::create([
                'aspiration_id' => $aspiration->id,
                'user_id' => $request->user()->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Notify all supporters
            $this->notifySupporters($aspiration, 'status_updated', [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);
        });

        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'aspiration_status_updated',
            'subject_type' => Aspiration::class,
            'subject_id' => $aspiration->id,
            'payload' => ['old' => $oldStatus, 'new' => $newStatus],
        ]);

        return back()->with('success', 'Status aspirasi diperbarui');
    }

    public function merge(Request $request, Aspiration $aspiration)
    {
        Gate::authorize('merge', $aspiration);

        $validated = $request->validate([
            'target_id' => 'required|exists:aspirations,id|different:aspiration',
        ]);

        $target = Aspiration::findOrFail($validated['target_id']);

        // Validate target is in same unit and not merged
        if ($target->organization_unit_id !== $aspiration->organization_unit_id) {
            return back()->withErrors(['target_id' => 'Aspirasi target harus dari unit yang sama']);
        }

        if ($target->isMerged()) {
            return back()->withErrors(['target_id' => 'Aspirasi target sudah digabungkan']);
        }

        if ($aspiration->isMerged()) {
            return back()->withErrors(['aspiration' => 'Aspirasi ini sudah digabungkan']);
        }

        DB::transaction(function () use ($aspiration, $target, $request) {
            // Mark as merged
            $aspiration->merged_into_id = $target->id;
            $aspiration->save();

            // Transfer supporters to target (skip duplicates)
            $existingSupports = AspirationSupport::where('aspiration_id', $target->id)
                ->pluck('member_id')
                ->toArray();

            $newSupports = AspirationSupport::where('aspiration_id', $aspiration->id)
                ->whereNotIn('member_id', $existingSupports)
                ->get();

            foreach ($newSupports as $support) {
                AspirationSupport::create([
                    'aspiration_id' => $target->id,
                    'member_id' => $support->member_id,
                ]);
            }

            // Recalculate target support count
            $target->recalculateSupportCount();

            // Log the merge
            AspirationUpdate::create([
                'aspiration_id' => $aspiration->id,
                'user_id' => $request->user()->id,
                'old_status' => $aspiration->status,
                'new_status' => 'merged',
                'notes' => "Digabungkan ke: {$target->title}",
            ]);

            // Notify original supporters about merge
            $this->notifySupporters($aspiration, 'merged', [
                'target_title' => $target->title,
                'target_id' => $target->id,
            ]);
        });

        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'aspiration_merged',
            'subject_type' => Aspiration::class,
            'subject_id' => $aspiration->id,
            'payload' => ['merged_into' => $target->id],
        ]);

        return redirect()->route('admin.aspirations.show', $target)
            ->with('success', 'Aspirasi berhasil digabungkan');
    }

    protected function notifySupporters(Aspiration $aspiration, string $type, array $data = []): void
    {
        // Get all supporters + aspiration owner
        $memberIds = $aspiration->supports()->pluck('member_id')->toArray();
        $memberIds[] = $aspiration->member_id;
        $memberIds = array_unique($memberIds);

        $users = \App\Models\User::whereIn('member_id', $memberIds)->get();

        foreach ($users as $user) {
            if ($type === 'status_updated') {
                $user->notify(new \App\Notifications\AspirationStatusUpdatedNotification($aspiration, $data));
            } elseif ($type === 'merged') {
                $user->notify(new \App\Notifications\AspirationMergedNotification($aspiration, $data));
            }
        }
    }
}
