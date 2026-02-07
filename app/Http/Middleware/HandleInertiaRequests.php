<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

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
        $hasMembersTable = Schema::hasTable('members');
        $user = $request->user();
        $memberExists = false;
        if ($user && $hasMembersTable) {
            // Prefer the direct relationship (members.user_id) which is used throughout the app (member portal/profile).
            // Fall back to member_id when present.
            $memberExists = (bool) ($user->member_id ?? false) || $user->member()->exists();
        }

        return [
            ...parent::share($request),
            'csrf_token' => csrf_token(),
            'features' => [
                'announcements' => (bool) config('features.announcements', true),
                'letters' => (bool) config('features.letters', true),
                'finance' => (bool) config('features.finance', true),
            ],
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'avatar' => $request->user()->avatar,
                    'member_photo_path' => function () use ($request) {
                        $user = $request->user();
                        if (! $user) {
                            return null;
                        }

                        $member = $user->member;
                        if (! $member && $user->member_id) {
                            $member = \App\Models\Member::find($user->member_id);
                        }

                        return $member?->photo_path;
                    },
                    'organization_unit_id' => $request->user()->organization_unit_id,
                    'organization_unit' => $request->user()->organizationUnit ? [
                        'id' => $request->user()->organizationUnit->id,
                        'name' => $request->user()->organizationUnit->name,
                        'code' => $request->user()->organizationUnit->code,
                    ] : null,
                    'member_id' => $request->user()->member_id,
                    'is_member' => $memberExists,
                    'role' => $request->user()->role ? [
                        'name' => $request->user()->role->name,
                        'label' => $request->user()->role->label,
                    ] : null,
                    'union_position' => function () use ($request) {
                        $user = $request->user();
                        if (! $user?->member_id) {
                            return null;
                        }

                        $member = $user->member;
                        if (! $member && $user->member_id) {
                            $member = \App\Models\Member::with('unionPosition')->find($user->member_id);
                        } else {
                            $member?->loadMissing('unionPosition');
                        }

                        $pos = $member?->unionPosition;
                        if (! $pos) {
                            return null;
                        }

                        return [
                            'id' => $pos->id,
                            'name' => $pos->name,
                            'code' => $pos->code,
                        ];
                    },
                    'employment_info' => function () use ($request) {
                        if (! Schema::hasTable('members')) {
                            return null;
                        }
                        $user = $request->user();
                        $member = $user->member;

                        // Fallback: search by member_id if relationship returns null but member_id exists
                        if (! $member && $user->member_id) {
                            $member = \App\Models\Member::find($user->member_id);
                        }
                        if (! $member || ! $member->company_join_date) {
                            return null;
                        }

                        $joinDate = $member->company_join_date;
                        $now = now();
                        $diff = $joinDate->diff($now);

                        $years = $diff->y;
                        $months = $diff->m;
                        $durationString = $years > 0 ? $years.' tahun' : 'Baru bergabung';

                        return [
                            'join_date' => $joinDate->translatedFormat('d M Y'),
                            'duration_years' => $years,
                            'duration_months' => $months,
                            'duration_string' => trim($durationString),
                        ];
                    },
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

                $user = $request->user();
                $roleName = $user?->role?->name;
                $isGlobal = $user?->hasGlobalAccess() ?? false;
                $unitId = $user?->currentUnitId();

                // Cache key suffix for scoped data
                $cacheKeySuffix = $isGlobal ? 'global' : "unit:{$unitId}";

                // Nationwide totals: shown to all users for confidence/visibility.
                // Unit-scoped totals are provided separately via members_unit_total and scoped queues below.
                $membersTotal = 0;
                $unitsTotal = 0;
                if ($hasMembers) {
                    $membersTotal = Cache::remember('metrics_members_total:global', 60, fn () => \App\Models\Member::count());
                }
                if ($hasUnits) {
                    $unitsTotal = Cache::remember('metrics_units_total:global', 60, fn () => \App\Models\OrganizationUnit::count());
                }

                // Scoped pending mutations
                $mutationsPending = 0;
                if ($hasMutations) {
                    if ($isGlobal) {
                        $mutationsPending = Cache::remember('metrics_mutations_pending:global', 60, fn () => \App\Models\MutationRequest::where('status', 'pending')->count());
                    } elseif ($unitId) {
                        $mutationsPending = Cache::remember("metrics_mutations_pending:unit:{$unitId}", 60, fn () => \App\Models\MutationRequest::where('status', 'pending')
                            ->where(fn ($q) => $q->where('from_unit_id', $unitId)->orWhere('to_unit_id', $unitId))
                            ->count());
                    }
                }

                // Scoped pending onboarding
                $onboardingPending = 0;
                if ($hasOnboarding) {
                    if ($isGlobal) {
                        $onboardingPending = Cache::remember('metrics_onboarding_pending:global', 60, fn () => \App\Models\PendingMember::where('status', 'pending')->count());
                    } elseif ($unitId) {
                        $onboardingPending = Cache::remember("metrics_onboarding_pending:unit:{$unitId}", 60, fn () => \App\Models\PendingMember::where('status', 'pending')->where('organization_unit_id', $unitId)->count());
                    }
                }

                // Scoped pending updates
                $updatesPending = 0;
                if ($hasUpdates) {
                    if ($isGlobal) {
                        $updatesPending = Cache::remember('metrics_updates_pending:global', 60, fn () => \App\Models\MemberUpdateRequest::where('status', 'pending')->count());
                    } elseif ($unitId) {
                        $updatesPending = Cache::remember("metrics_updates_pending:unit:{$unitId}", 60, fn () => \App\Models\MemberUpdateRequest::where('status', 'pending')
                            ->whereHas('member', fn ($q) => $q->where('organization_unit_id', $unitId))
                            ->count());
                    }
                }

                return [
                    'members_total' => $membersTotal,
                    'units_total' => $unitsTotal,
                    'mutations_pending' => $mutationsPending,
                    'onboarding_pending' => $onboardingPending,
                    'updates_pending' => $updatesPending,
                    'aspirations_pending' => Schema::hasTable('aspirations') ? function () use ($user, $roleName, $unitId, $isGlobal) {
                        if (! $user) {
                            return 0;
                        }
                        if ($isGlobal) {
                            return \App\Models\Aspiration::where('status', 'new')->notMerged()->count();
                        }
                        if ($roleName === 'admin_unit' && $unitId) {
                            return \App\Models\Aspiration::where('organization_unit_id', $unitId)->where('status', 'new')->notMerged()->count();
                        }

                        return 0;
                    } : 0,
                    'notifications_unread' => ($userId && $hasNotifications) ? optional($request->user())->unreadNotifications()->count() : 0,
                    'members_unit_total' => ($hasMembers && $unitId)
                        ? \App\Models\Member::where('organization_unit_id', $unitId)->count()
                        : 0,
                    'is_global' => $isGlobal,
                ];
            },
            'kpi' => function () {
                $latest = Schema::hasTable('kpi_snapshots') ? \App\Models\KpiSnapshot::orderByDesc('calculated_at')->first() : null;
                if (! $latest) {
                    return ['completeness_pct' => 0, 'mutation_sla_breach_pct' => 0, 'card_downloads' => 0];
                }

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
