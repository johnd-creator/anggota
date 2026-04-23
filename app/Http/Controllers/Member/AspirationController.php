<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Aspiration;
use App\Models\AspirationCategory;
use App\Models\AspirationSupport;
use App\Models\AspirationTag;
use App\Models\ActivityLog;
use App\Notifications\AspirationCreatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AspirationController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Aspiration::class);

        $user = $request->user();
        $member = $user->member;

        // Member-facing pages should follow the linked member's unit when available.
        // Central roles may carry a DPP operational unit, but that should not override
        // the unit where their member profile belongs.
        $unitId = $user->memberContextUnitId();

        $query = Aspiration::with(['category', 'member:id,full_name', 'tags', 'user:id,name']) // Eager load user too
            ->notMerged();

        if ($unitId) {
            $query->byUnit($unitId);
        } elseif (!$user->canViewGlobalScope()) {
            // If no unit and not global, return empty or redirect
            return redirect()->route('dashboard')->with('error', 'Unit tidak ditemukan');
        }

        // If global access and no unit, we show ALL (dashboard behavior).
        // But UI says "Aspirasi Unit". That's fine.

        // Filters
        if ($categoryId = $request->get('category')) {
            $query->where('category_id', $categoryId);
        }

        if ($status = $request->get('status')) {
            $query->byStatus($status);
        }

        // Sorting
        $sort = $request->get('sort', 'latest');
        if ($sort === 'popular') {
            $query->orderByDesc('support_count');
        } else {
            $query->latest();
        }

        $aspirations = $query->paginate(10)->withQueryString();

        // Add support status for current member
        $aspirationIds = $aspirations->pluck('id');
        $supportedIds = [];
        if ($member) {
            $supportedIds = AspirationSupport::where('member_id', $member->id)
                ->whereIn('aspiration_id', $aspirationIds)
                ->pluck('aspiration_id')
                ->toArray();
        }

        $aspirations->getCollection()->transform(function ($aspiration) use ($supportedIds, $member, $user) {
            $aspiration->setAttribute('is_supported', in_array($aspiration->id, $supportedIds, true));
            $aspiration->setAttribute('is_own', $member ? ($aspiration->member_id === $member->id) : false);
            $aspiration->setAttribute('can_view_creator', $user->can('viewCreatorInfo', $aspiration));
            return $aspiration;
        });

        return Inertia::render('Member/Aspirations/Index', [
            'aspirations' => $aspirations,
            'categories' => AspirationCategory::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['category', 'status', 'sort']),
        ]);
    }

    public function create()
    {
        Gate::authorize('create', Aspiration::class);

        return Inertia::render('Member/Aspirations/Create', [
            'categories' => AspirationCategory::orderBy('name')->get(['id', 'name']),
            'existingTags' => AspirationTag::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Aspiration::class);

        $validated = $request->validate([
            'category_id' => 'required|exists:aspiration_categories,id',
            'title' => 'required|string|max:255',
            'body' => 'required|string|min:10',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'is_anonymous' => 'boolean',
        ]);

        $user = $request->user();
        $member = $user->member;

        // If not a member, ensure has admin role and use their unit
        if (!$member) {
            if (!$user->hasRole(['super_admin', 'admin_pusat', 'admin_unit', 'pengurus_pusat'])) {
                return back()->withErrors(['member' => 'Profil anggota tidak ditemukan']);
            }
            // Member-facing context should use the linked member's origin unit first.
            $unitId = $user->memberContextUnitId();
            if (!$unitId && !$user->canViewGlobalScope()) {
                return back()->withErrors(['unit' => 'Unit organisasi tidak ditemukan']);
            }
            // Global admins without unit cannot create aspirations in member view
            if (!$unitId) {
                return back()->withErrors(['unit' => 'Pilih unit terlebih dahulu untuk membuat aspirasi']);
            }
        } else {
            $unitId = $user->memberContextUnitId();
        }

        $aspiration = DB::transaction(function () use ($validated, $member, $user, $unitId) {
            $aspiration = Aspiration::create([
                'member_id' => $member?->id,
                'user_id' => $user->id,
                'organization_unit_id' => $unitId,
                'category_id' => $validated['category_id'],
                'title' => $validated['title'],
                'body' => $validated['body'],
                'status' => 'new',
                'support_count' => 0,
                'is_anonymous' => $validated['is_anonymous'] ?? false,
            ]);

            // Handle tags
            if (!empty($validated['tags'])) {
                $tagIds = [];
                foreach ($validated['tags'] as $tagName) {
                    $tag = AspirationTag::firstOrCreate(['name' => strtolower(trim($tagName))]);
                    $tagIds[] = $tag->id;
                }
                $aspiration->tags()->sync($tagIds);
            }

            // Notify admins (unit admins + global admins)
            try {
                $unitAdminUsers = \App\Models\User::whereHas('role', fn($q) => $q->where('name', 'admin_unit'))
                    ->where('organization_unit_id', $unitId)
                    ->get();
                $globalAdmins = \App\Models\User::whereHas('role', fn($q) => $q->whereIn('name', ['super_admin', 'admin_pusat', 'pengurus_pusat']))->get();
                $targets = $unitAdminUsers->merge($globalAdmins)->unique('id');
                // Filter out current user
                $targets = $targets->reject(fn($u) => $u->id === $user->id);

                foreach ($targets as $u) {
                    $u->notify(new AspirationCreatedNotification($aspiration));
                }
            } catch (\Throwable $e) {
                // ignore notification failures
            }

            return $aspiration;
        });

        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'aspiration_created',
            'subject_type' => Aspiration::class,
            'subject_id' => $aspiration->id,
            'payload' => ['title' => $aspiration->title],
        ]);

        return redirect()->route('member.aspirations.index')
            ->with('success', 'Aspirasi berhasil dikirim');
    }

    public function show(Aspiration $aspiration)
    {
        Gate::authorize('view', $aspiration);

        $aspiration->load(['category', 'member:id,full_name', 'tags', 'mergedInto.member:id,full_name']);

        $user = request()->user();
        $member = $user->member;

        $aspiration->is_supported = $member ? $aspiration->isSupporter($member) : false;
        $aspiration->is_own = $member && $aspiration->member_id === $member->id;
        $aspiration->can_view_creator = $user->can('viewCreatorInfo', $aspiration);

        return Inertia::render('Member/Aspirations/Show', [
            'aspiration' => $aspiration,
        ]);
    }

    public function support(Request $request, Aspiration $aspiration)
    {
        Gate::authorize('support', $aspiration);

        $member = $request->user()->member;

        if (!$member) {
            return back()->withErrors(['error' => 'Hanya anggota yang memiliki profil yang dapat memberikan dukungan.']);
        }

        // Check if already supported
        $existing = AspirationSupport::where('aspiration_id', $aspiration->id)
            ->where('member_id', $member->id)
            ->first();

        if ($existing) {
            // Toggle off - remove support
            $existing->delete();
            $aspiration->decrementSupport();

            return back()->with('success', 'Dukungan dicabut');
        }

        // Add support
        AspirationSupport::create([
            'aspiration_id' => $aspiration->id,
            'member_id' => $member->id,
        ]);
        $aspiration->incrementSupport();

        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'aspiration_supported',
            'subject_type' => Aspiration::class,
            'subject_id' => $aspiration->id,
        ]);

        return back()->with('success', 'Anda mendukung aspirasi ini');
    }
}
