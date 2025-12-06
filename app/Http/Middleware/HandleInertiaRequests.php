<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        // Nonaktifkan versioning Inertia (hindari 409 saat pengembangan)
        return null;
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'avatar' => $request->user()->avatar,
                    'organization_unit_id' => $request->user()->organization_unit_id,
                    'organization_unit' => $request->user()->organizationUnit ? [
                        'id' => $request->user()->organizationUnit->id,
                        'name' => $request->user()->organizationUnit->name,
                        'code' => $request->user()->organizationUnit->code,
                    ] : null,
                    'member_id' => $request->user()->member_id,
                    'is_member' => !is_null($request->user()->member_id),
                    'role' => $request->user()->role ? [
                        'name' => $request->user()->role->name,
                        'label' => $request->user()->role->label,
                    ] : null,
                ] : null,
            ],
            'counters' => function () use ($request) {
                $userId = $request->user()?->id;
                $hasMembers = Schema::hasTable('members');
                $hasUnits = Schema::hasTable('organization_units');
                $hasMutations = Schema::hasTable('mutation_requests');
                $hasOnboarding = Schema::hasTable('pending_members');
                $hasUpdates = Schema::hasTable('member_update_requests');
                $hasNotifications = Schema::hasTable('notifications');

                return [
                    'members_total' => $hasMembers ? Cache::remember('metrics_members_total', 60, fn() => \App\Models\Member::count()) : 0,
                    'units_total' => $hasUnits ? Cache::remember('metrics_units_total', 60, fn() => \App\Models\OrganizationUnit::count()) : 0,
                    'mutations_pending' => $hasMutations ? Cache::remember('metrics_mutations_pending', 60, fn() => \App\Models\MutationRequest::where('status','pending')->count()) : 0,
                    'onboarding_pending' => $hasOnboarding ? Cache::remember('metrics_onboarding_pending', 60, fn() => \App\Models\PendingMember::where('status','pending')->count()) : 0,
                    'updates_pending' => $hasUpdates ? Cache::remember('metrics_updates_pending', 60, fn() => \App\Models\MemberUpdateRequest::where('status','pending')->count()) : 0,
                    'notifications_unread' => ($userId && $hasNotifications) ? optional($request->user())->unreadNotifications()->count() : 0,
                    'members_unit_total' => ($hasMembers && $request->user() && $request->user()->role && $request->user()->role->name === 'admin_unit' && $request->user()->organization_unit_id)
                        ? \App\Models\Member::where('organization_unit_id', $request->user()->organization_unit_id)->count()
                        : 0,
                ];
            },
            'kpi' => function(){
                $latest = Schema::hasTable('kpi_snapshots') ? \App\Models\KpiSnapshot::orderByDesc('calculated_at')->first() : null;
                if (!$latest) return [ 'completeness_pct' => 0, 'mutation_sla_breach_pct' => 0, 'card_downloads' => 0 ];
                return [
                    'completeness_pct' => (float) $latest->completeness_pct,
                    'mutation_sla_breach_pct' => (float) $latest->mutation_sla_breach_pct,
                    'card_downloads' => (int) $latest->card_downloads,
                ];
            },
            'onboarding' => function () use ($request) {
                $hasOnboarding = Schema::hasTable('pending_members');
                if ($request->user() && $hasOnboarding) {
                    $pending = \App\Models\PendingMember::where('user_id', $request->user()->id)->latest()->first();
                    if ($pending) {
                        return [
                            'status' => $pending->status,
                            'notes' => $pending->notes,
                            'unit_id' => $pending->organization_unit_id,
                            'updated_at' => $pending->updated_at,
                        ];
                    }
                }
                return null;
            },
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
                'import_errors' => session('import_errors'),
                'import_summary' => session('import_summary'),
            ],
        ];
    }
}
