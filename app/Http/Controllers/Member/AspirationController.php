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

        // If not member, determine unit from user
        $unitId = $member ? $member->organization_unit_id : $user->organization_unit_id;

        // Global admins (super_admin, admin_pusat) might not have unit, show all?
        // But this is "Member View". Maybe show all if global admin?
        // User request: "ke 3 role ini seharusnya dapat menyampaikan".
        // Let's assume for global admins in "member view" they see everything or just their assigned unit?
        // Given it's "Aspirasi Unit", let's defaults to their unit if exists, else all?
        // Let's stick to unit-based for now. If global admin has no unit, they see everything?

        $query = Aspiration::with(['category', 'member:id,full_name', 'tags', 'user:id,name']) // Eager load user too
            ->notMerged();

        if ($unitId) {
            $query->byUnit($unitId);
        } else if (!$user->hasGlobalAccess()) {
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
        $supportedIds = AspirationSupport::where('member_id', $member->id)
            ->whereIn('aspiration_id', $aspirationIds)
            ->pluck('aspiration_id')
            ->toArray();

        $aspirations->getCollection()->transform(function ($aspiration) use ($supportedIds, $member) {
            $aspiration->setAttribute('is_supported', in_array($aspiration->id, $supportedIds));
            $aspiration->setAttribute('is_own', $aspiration->member_id === $member->id);
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
        ]);

        $user = $request->user();
        $member = $user->member;

        // If not a member, ensure has admin role and use their unit
        if (!$member) {
            if (!$user->hasRole(['super_admin', 'admin_pusat', 'admin_unit'])) {
                return back()->withErrors(['member' => 'Profil anggota tidak ditemukan']);
            }
            // For admin_pusat/super_admin, they might not have a unit. If so, require unit selection or default?
            // User requirement: "ke 3 role ini seharusnya dapat menyampaikan".
            // If super_admin has no unit, we default to null? But OrganizationUnitId is required in database.
            // Let's rely on $user->organization_unit_id. If missing, we block or default.
            if (!$user->organization_unit_id && !$user->hasGlobalAccess()) {
                return back()->withErrors(['unit' => 'Unit organisasi tidak ditemukan']);
            }
        }

        $unitId = $member ? $member->organization_unit_id : ($user->organization_unit_id ?? \App\Models\OrganizationUnit::first()->id); // Fallback for global admins if no unit assigned

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
                $globalAdmins = \App\Models\User::whereHas('role', fn($q) => $q->whereIn('name', ['super_admin', 'admin_pusat']))->get();
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
