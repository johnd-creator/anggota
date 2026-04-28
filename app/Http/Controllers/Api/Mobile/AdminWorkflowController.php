<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Mobile\Concerns\MobileApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\MemberResource;
use App\Models\Member;
use App\Models\MemberUpdateRequest;
use App\Models\MutationRequest;
use App\Models\PendingMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminWorkflowController extends Controller
{
    use MobileApiHelpers;

    public function members(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Member::class);

        $query = Member::with(['unit', 'unionPosition'])->latest();
        $this->scopeUnitQuery($query, $request->user());
        $this->applyMemberFilters($query, $request);

        $paginator = $query->paginate($this->perPage($request));
        $paginator->getCollection()->transform(fn (Member $member) => new MemberResource($member));

        return $this->paginated($paginator, 'members');
    }

    public function memberSearch(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Member::class);
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $query = Member::with('unit:id,name,code');
        $this->scopeUnitQuery($query, $request->user());
        $this->applyMemberFilters($query, $request);

        return response()->json([
            'items' => $query->orderBy('full_name')->limit(30)->get()->map(fn (Member $member) => [
                'id' => $member->id,
                'full_name' => $member->full_name,
                'kta_number' => $member->kta_number,
                'nra' => $member->nra,
                'unit' => $member->unit?->only(['id', 'name', 'code']),
            ])->values(),
        ]);
    }

    public function memberShow(Member $member): JsonResponse
    {
        Gate::authorize('view', $member);

        return response()->json(['member' => new MemberResource($member->load(['unit', 'unionPosition', 'documents']))]);
    }

    public function memberStore(Request $request): JsonResponse
    {
        Gate::authorize('create', Member::class);
        $data = $this->validatedMember($request);
        Gate::authorize('createInUnit', [Member::class, (int) $data['organization_unit_id']]);

        $member = Member::create($data);

        return response()->json(['status' => 'ok', 'member' => new MemberResource($member->load(['unit', 'unionPosition']))], 201);
    }

    public function memberUpdate(Request $request, Member $member): JsonResponse
    {
        Gate::authorize('update', $member);
        $member->update($this->validatedMember($request, $member));

        return $this->ok(['member' => new MemberResource($member->fresh()->load(['unit', 'unionPosition', 'documents']))]);
    }

    public function memberExportRequest(Request $request): JsonResponse
    {
        Gate::authorize('export', Member::class);

        return response()->json([
            'status' => 'queued',
            'message' => 'Permintaan export anggota diterima. Worker/export async dapat memakai filter yang sama.',
            'filters' => $request->only(['q', 'status', 'organization_unit_id']),
        ], 202);
    }

    public function onboarding(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PendingMember::class);
        $query = PendingMember::with('unit')->latest();
        if (! $request->user()->canViewGlobalScope()) {
            $query->where('organization_unit_id', $request->user()->currentUnitId());
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $this->paginated($query->paginate($this->perPage($request)), 'pending_members');
    }

    public function approveOnboarding(Request $request, PendingMember $pending): JsonResponse
    {
        Gate::authorize('approve', $pending);
        $data = $request->validate([
            'organization_unit_id' => ['nullable', 'exists:organization_units,id'],
            'join_date' => ['nullable', 'date'],
        ]);
        $unitId = $request->user()->canViewGlobalScope()
            ? (int) ($data['organization_unit_id'] ?? $pending->organization_unit_id)
            : $request->user()->currentUnitId();

        $member = DB::transaction(function () use ($pending, $request, $unitId, $data) {
            $member = Member::create([
                'user_id' => $pending->user_id,
                'full_name' => $pending->name,
                'email' => $pending->email,
                'organization_unit_id' => $unitId,
                'status' => 'active',
                'join_date' => $data['join_date'] ?? now()->toDateString(),
            ]);
            $pending->update(['status' => 'approved', 'reviewer_id' => $request->user()->id]);

            return $member;
        });

        return $this->ok(['member' => new MemberResource($member->load('unit'))]);
    }

    public function rejectOnboarding(Request $request, PendingMember $pending): JsonResponse
    {
        Gate::authorize('reject', $pending);
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);
        $pending->update(['status' => 'rejected', 'reviewer_id' => $request->user()->id, 'notes' => $validated['notes'] ?? $pending->notes]);

        return $this->ok();
    }

    public function updates(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', MemberUpdateRequest::class);
        $query = MemberUpdateRequest::with('member.unit')->latest();
        if (! $request->user()->canViewGlobalScope()) {
            $query->whereHas('member', fn ($q) => $q->where('organization_unit_id', $request->user()->currentUnitId()));
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $this->paginated($query->paginate($this->perPage($request)), 'update_requests');
    }

    public function approveUpdate(Request $request, MemberUpdateRequest $update): JsonResponse
    {
        Gate::authorize('approve', $update);

        DB::transaction(function () use ($update, $request) {
            $update->member?->update($update->new_data ?? []);
            $update->update(['status' => 'approved', 'reviewer_id' => $request->user()->id]);
        });

        return $this->ok();
    }

    public function rejectUpdate(Request $request, MemberUpdateRequest $update): JsonResponse
    {
        Gate::authorize('reject', $update);
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);
        $update->update(['status' => 'rejected', 'reviewer_id' => $request->user()->id, 'notes' => $validated['notes'] ?? null]);

        return $this->ok();
    }

    public function mutations(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', MutationRequest::class);
        $query = MutationRequest::with(['member:id,full_name,kta_number,organization_unit_id', 'fromUnit:id,name,code', 'toUnit:id,name,code'])->latest();
        if (! $request->user()->canViewGlobalScope()) {
            $unitId = $request->user()->currentUnitId();
            $query->where(fn ($q) => $q->where('from_unit_id', $unitId)->orWhere('to_unit_id', $unitId));
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $this->paginated($query->paginate($this->perPage($request)), 'mutations');
    }

    public function mutationStore(Request $request): JsonResponse
    {
        Gate::authorize('create', MutationRequest::class);
        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'to_unit_id' => ['required', 'exists:organization_units,id'],
            'effective_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $member = Member::findOrFail($data['member_id']);
        Gate::authorize('createFor', [MutationRequest::class, $member]);

        $mutation = MutationRequest::create($data + [
            'from_unit_id' => $member->organization_unit_id,
            'status' => 'pending',
            'submitted_by' => $request->user()->id,
        ]);

        return response()->json(['status' => 'ok', 'mutation' => $mutation->load(['member', 'fromUnit', 'toUnit'])], 201);
    }

    public function mutationShow(MutationRequest $mutation): JsonResponse
    {
        Gate::authorize('view', $mutation);

        return response()->json(['mutation' => $mutation->load(['member', 'fromUnit', 'toUnit'])]);
    }

    public function approveMutation(Request $request, MutationRequest $mutation): JsonResponse
    {
        Gate::authorize('approve', $mutation);
        DB::transaction(function () use ($mutation, $request) {
            $mutation->member?->update(['organization_unit_id' => $mutation->to_unit_id]);
            $mutation->update(['status' => 'approved', 'approved_by' => $request->user()->id]);
        });

        return $this->ok();
    }

    public function rejectMutation(Request $request, MutationRequest $mutation): JsonResponse
    {
        Gate::authorize('reject', $mutation);
        $mutation->update(['status' => 'rejected', 'approved_by' => $request->user()->id]);

        return $this->ok();
    }

    public function cancelMutation(Request $request, MutationRequest $mutation): JsonResponse
    {
        Gate::authorize('cancel', $mutation);
        $mutation->update(['status' => 'cancelled', 'approved_by' => $request->user()->id]);

        return $this->ok();
    }

    private function applyMemberFilters($query, Request $request): void
    {
        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('kta_number', 'like', "%{$search}%")
                ->orWhere('nra', 'like', "%{$search}%")
                ->orWhere('nip', 'like', "%{$search}%"));
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($request->user()->canViewGlobalScope() && $unitId = $request->query('organization_unit_id')) {
            $query->where('organization_unit_id', $unitId);
        }
    }

    private function validatedMember(Request $request, ?Member $member = null): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'nip' => ['nullable', 'string', 'max:100', Rule::unique('members')->ignore($member?->id)],
            'kta_number' => ['nullable', 'string', 'max:100', Rule::unique('members')->ignore($member?->id)],
            'nra' => ['nullable', 'string', 'max:100', Rule::unique('members')->ignore($member?->id)],
            'organization_unit_id' => ['required', 'exists:organization_units,id'],
            'union_position_id' => ['nullable', 'exists:union_positions,id'],
            'status' => ['required', Rule::in(['active', 'inactive', 'pending'])],
            'join_date' => ['nullable', 'date'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
