<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Mobile\Concerns\ResolvesMobileMember;
use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\AspirationResource;
use App\Models\ActivityLog;
use App\Models\Aspiration;
use App\Models\AspirationCategory;
use App\Models\AspirationSupport;
use App\Models\AspirationTag;
use App\Models\User;
use App\Notifications\AspirationCreatedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AspirationController extends Controller
{
    use ResolvesMobileMember;

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Aspiration::class);

        $user = $request->user();
        $member = $this->mobileMember($user);
        $unitId = $user->memberContextUnitId();

        $query = Aspiration::with(['category', 'member:id,full_name', 'tags', 'user:id,name'])
            ->notMerged();

        if ($unitId) {
            $query->byUnit($unitId);
        } elseif (! $user->canViewGlobalScope()) {
            return response()->json(['message' => 'Unit organisasi tidak ditemukan.'], 422);
        }

        if ($categoryId = $request->query('category')) {
            $query->where('category_id', $categoryId);
        }

        if ($status = $request->query('status')) {
            $query->byStatus($status);
        }

        $request->query('sort') === 'popular'
            ? $query->orderByDesc('support_count')
            : $query->latest();

        $aspirations = $query->paginate((int) $request->integer('per_page', 10));
        $aspirationIds = $aspirations->getCollection()->pluck('id');
        $supportedIds = $member
            ? AspirationSupport::where('member_id', $member->id)->whereIn('aspiration_id', $aspirationIds)->pluck('aspiration_id')->all()
            : [];

        $aspirations->getCollection()->transform(function (Aspiration $aspiration) use ($supportedIds, $member, $user) {
            $aspiration->setAttribute('is_supported', in_array($aspiration->id, $supportedIds, true));
            $aspiration->setAttribute('is_own', $member ? $aspiration->member_id === $member->id : false);
            $aspiration->setAttribute('can_view_creator', $user->can('viewCreatorInfo', $aspiration));

            return $aspiration;
        });

        return response()->json([
            'items' => AspirationResource::collection($aspirations->getCollection()),
            'meta' => [
                'current_page' => $aspirations->currentPage(),
                'last_page' => $aspirations->lastPage(),
                'per_page' => $aspirations->perPage(),
                'total' => $aspirations->total(),
            ],
            'categories' => AspirationCategory::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['category', 'status', 'sort']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Aspiration::class);

        $validated = $request->validate([
            'category_id' => ['required', 'exists:aspiration_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'min:10'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'is_anonymous' => ['boolean'],
        ]);

        $user = $request->user();
        $member = $this->mobileMember($user);
        $unitId = $user->memberContextUnitId();

        if (! $member && ! $user->hasRole(['super_admin', 'admin_pusat', 'admin_unit', 'pengurus_pusat'])) {
            return response()->json(['message' => 'Profil anggota tidak ditemukan.'], 404);
        }

        if (! $unitId) {
            return response()->json(['message' => 'Unit organisasi tidak ditemukan.'], 422);
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

            if (! empty($validated['tags'])) {
                $tagIds = collect($validated['tags'])
                    ->map(fn ($name) => strtolower(trim($name)))
                    ->filter()
                    ->unique()
                    ->map(fn ($name) => AspirationTag::firstOrCreate(['name' => $name])->id)
                    ->all();

                $aspiration->tags()->sync($tagIds);
            }

            $this->notifyAdmins($aspiration, $unitId, $user);

            return $aspiration;
        });

        ActivityLog::create([
            'actor_id' => $user->id,
            'action' => 'aspiration_created',
            'subject_type' => Aspiration::class,
            'subject_id' => $aspiration->id,
            'payload' => ['title' => $aspiration->title],
        ]);

        $aspiration->load(['category', 'member:id,full_name', 'tags', 'user:id,name']);
        $aspiration->setAttribute('is_supported', false);
        $aspiration->setAttribute('is_own', true);
        $aspiration->setAttribute('can_view_creator', $user->can('viewCreatorInfo', $aspiration));

        return response()->json([
            'status' => 'ok',
            'aspiration' => new AspirationResource($aspiration),
        ], 201);
    }

    public function show(Request $request, Aspiration $aspiration): JsonResponse
    {
        Gate::authorize('view', $aspiration);

        $aspiration->load(['category', 'member:id,full_name', 'tags', 'user:id,name', 'mergedInto.member:id,full_name']);
        $member = $this->mobileMember($request->user());

        $aspiration->setAttribute('is_supported', $member ? $aspiration->isSupporter($member) : false);
        $aspiration->setAttribute('is_own', $member ? $aspiration->member_id === $member->id : false);
        $aspiration->setAttribute('can_view_creator', $request->user()->can('viewCreatorInfo', $aspiration));

        return response()->json([
            'aspiration' => new AspirationResource($aspiration),
        ]);
    }

    public function support(Request $request, Aspiration $aspiration): JsonResponse
    {
        Gate::authorize('support', $aspiration);

        $member = $this->mobileMember($request->user());
        if (! $member) {
            return response()->json(['message' => 'Profil anggota tidak ditemukan.'], 404);
        }

        $existing = AspirationSupport::where('aspiration_id', $aspiration->id)
            ->where('member_id', $member->id)
            ->first();

        if (! $existing) {
            AspirationSupport::create([
                'aspiration_id' => $aspiration->id,
                'member_id' => $member->id,
            ]);
            $aspiration->incrementSupport();
        }

        return response()->json([
            'status' => 'ok',
            'support_count' => (int) $aspiration->fresh()->support_count,
            'is_supported' => true,
        ]);
    }

    public function unsupport(Request $request, Aspiration $aspiration): JsonResponse
    {
        Gate::authorize('support', $aspiration);

        $member = $this->mobileMember($request->user());
        if (! $member) {
            return response()->json(['message' => 'Profil anggota tidak ditemukan.'], 404);
        }

        $deleted = AspirationSupport::where('aspiration_id', $aspiration->id)
            ->where('member_id', $member->id)
            ->delete();

        if ($deleted > 0) {
            $aspiration->decrementSupport();
        }

        return response()->json([
            'status' => 'ok',
            'support_count' => (int) $aspiration->fresh()->support_count,
            'is_supported' => false,
        ]);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'items' => AspirationCategory::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function tags(): JsonResponse
    {
        return response()->json([
            'items' => AspirationTag::orderBy('name')->pluck('name')->values(),
        ]);
    }

    private function notifyAdmins(Aspiration $aspiration, int $unitId, User $actor): void
    {
        try {
            $unitAdmins = User::whereHas('role', fn ($query) => $query->where('name', 'admin_unit'))
                ->where('organization_unit_id', $unitId)
                ->get();
            $globalAdmins = User::whereHas('role', fn ($query) => $query->whereIn('name', ['super_admin', 'admin_pusat', 'pengurus_pusat']))
                ->get();

            $unitAdmins->merge($globalAdmins)
                ->unique('id')
                ->reject(fn (User $user) => $user->id === $actor->id)
                ->each(fn (User $user) => $user->notify(new AspirationCreatedNotification($aspiration)));
        } catch (\Throwable) {
            // Mobile submission should not fail when notification delivery fails.
        }
    }
}
