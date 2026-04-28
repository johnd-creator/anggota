<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Mobile\Concerns\MobileApiHelpers;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminOpsController extends Controller
{
    use MobileApiHelpers;

    public function roles(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403);

        return response()->json(['items' => Role::withCount('users')->orderBy('name')->get()]);
    }

    public function assignRole(Request $request, Role $role): JsonResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'organization_unit_id' => ['nullable', 'exists:organization_units,id'],
        ]);
        User::whereKey($data['user_id'])->update([
            'role_id' => $role->id,
            'organization_unit_id' => $data['organization_unit_id'] ?? null,
        ]);

        return $this->ok();
    }

    public function users(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        $query = User::with(['role', 'organizationUnit'])->latest();
        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        return $this->paginated($query->paginate($this->perPage($request)), 'users');
    }

    public function userShow(User $user): JsonResponse
    {
        Gate::authorize('view', $user);

        return response()->json(['user' => $user->load(['role', 'organizationUnit', 'linkedMember.unit'])]);
    }

    public function userUpdate(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role_id' => ['required', 'exists:roles,id'],
            'organization_unit_id' => ['nullable', 'exists:organization_units,id'],
        ]);
        $user->update($data);

        return $this->ok(['user' => $user->fresh()->load(['role', 'organizationUnit'])]);
    }

    public function sessions(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', UserSession::class);
        $query = UserSession::with('user:id,name,email')->latest('last_activity');

        return $this->paginated($query->paginate($this->perPage($request)), 'sessions');
    }

    public function revokeSession(UserSession $session): JsonResponse
    {
        Gate::authorize('delete', $session);
        $session->delete();

        return $this->ok();
    }

    public function auditLogs(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AuditLog::class);
        $query = AuditLog::latest();
        if (! $request->user()->hasRole('super_admin')) {
            $query->where('organization_unit_id', $request->user()->currentUnitId());
        }

        return $this->paginated($query->paginate($this->perPage($request)), 'audit_logs');
    }

    public function activityLogs(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403);

        return $this->paginated(ActivityLog::latest()->paginate($this->perPage($request)), 'activity_logs');
    }

    public function ops(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403);

        return response()->json([
            'backup' => [
                'status' => config('backup.enabled', false) ? 'configured' : 'not_configured',
                'last_run_at' => null,
            ],
            'queue' => [
                'connection' => config('queue.default'),
            ],
        ]);
    }
}
