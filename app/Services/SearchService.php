<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Aspiration;
use App\Models\DuesPayment;
use App\Models\FinanceLedger;
use App\Models\Letter;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SearchService
{
    /**
     * Search across allowed domains based on user role.
     */
    public function search(User $user, string $query, array $types = [], int $limit = 5): array
    {
        $query = trim($query);
        if (strlen($query) < 2) {
            return [];
        }

        $results = [];
        $allowedTypes = $this->getAllowedTypes($user);
        $typesToSearch = empty($types) ? $allowedTypes : array_intersect($types, $allowedTypes);

        if (in_array('announcements', $typesToSearch)) {
            $results['announcements'] = $this->searchAnnouncements($user, $query, $limit);
        }

        if (in_array('letters', $typesToSearch)) {
            $results['letters'] = $this->searchLetters($user, $query, $limit);
        }

        if (in_array('aspirations', $typesToSearch)) {
            $results['aspirations'] = $this->searchAspirations($user, $query, $limit);
        }

        if (in_array('members', $typesToSearch)) {
            $results['members'] = $this->searchMembers($user, $query, $limit);
        }

        if (in_array('users', $typesToSearch)) {
            $results['users'] = $this->searchUsers($user, $query, $limit);
        }

        if (in_array('dues_payments', $typesToSearch)) {
            $results['dues_payments'] = $this->searchDuesPayments($user, $query, $limit);
        }

        if (in_array('finance_ledgers', $typesToSearch)) {
            $results['finance_ledgers'] = $this->searchFinanceLedgers($user, $query, $limit);
        }

        return [
            'query' => $query,
            'results' => $results,
            'allowed_types' => $allowedTypes,
        ];
    }

    public function allowedTypes(User $user): array
    {
        return $this->getAllowedTypes($user);
    }

    public function paginate(User $user, string $q, string $type, int $perPage = 15): \Illuminate\Pagination\LengthAwarePaginator
    {
        $q = trim($q);
        if (!$q) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        $allowed = $this->getAllowedTypes($user);
        if (!in_array($type, $allowed)) {
            abort(403, 'Unauthorized search type');
        }

        switch ($type) {
            case 'announcements':
                return $this->paginateAnnouncements($user, $q, $perPage);
            case 'letters':
                return $this->paginateLetters($user, $q, $perPage);
            case 'aspirations':
                return $this->paginateAspirations($user, $q, $perPage);
            case 'members':
                return $this->paginateMembers($user, $q, $perPage);
            case 'users':
                return $this->paginateUsers($user, $q, $perPage);
            case 'dues_payments':
                return $this->paginateDuesPayments($user, $q, $perPage);
            case 'finance_ledgers':
                return $this->paginateFinanceLedgers($user, $q, $perPage);
            default:
                return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
    }

    // --- Base Queries (Strict Scoping) ---

    protected function baseAnnouncementsQuery(User $user): Builder
    {
        return Announcement::visibleTo($user);
    }

    protected function baseLettersQuery(User $user): Builder
    {
        $unitId = $user->currentUnitId();
        $roleName = $this->roleName($user);

        $query = Letter::query()
            ->with(['fromUnit'])
            ->whereIn('status', ['submitted', 'approved', 'sent', 'archived']);

        if (in_array($roleName, ['anggota', 'bendahara'])) {
            $query->where(function ($sq) use ($user, $unitId) {
                if ($user->member_id) {
                    $sq->orWhere(function ($sub) use ($user) {
                        $sub->where('to_type', 'member')->where('to_member_id', $user->member_id);
                    });
                }
                if ($unitId) {
                    $sq->orWhere(function ($sub) use ($unitId) {
                        $sub->where('to_type', 'unit')->where('to_unit_id', $unitId);
                    });
                }
            });
        } elseif ($roleName === 'admin_unit') {
            $query->where(function ($sq) use ($unitId) {
                if ($unitId) {
                    $sq->where('to_type', 'unit')->where('to_unit_id', $unitId);
                } else {
                    $sq->whereRaw('0 = 1');
                }
            });
        } else {
            // Admin Pusat / Super Admin -> see letters to admin_pusat
            $query->where('to_type', 'admin_pusat');
        }

        return $query;
    }

    protected function baseAspirationsQuery(User $user): Builder
    {
        $roleName = $this->roleName($user);
        $query = Aspiration::query();

        if ($roleName === 'anggota') {
            $query->where('member_id', $user->member_id);
        } elseif (in_array($roleName, ['admin_unit', 'bendahara'])) {
            $unitId = $user->currentUnitId();
            if ($unitId) {
                $query->where('organization_unit_id', $unitId);
            }
        }
        return $query;
    }

    protected function baseMembersQuery(User $user): Builder
    {
        $roleName = $this->roleName($user);
        $query = Member::query();

        if (in_array($roleName, ['admin_unit', 'bendahara'])) {
            $unitId = $user->currentUnitId();
            if ($unitId) {
                $query->where('organization_unit_id', $unitId);
            } else {
                // If they have no unit, they shouldn't see members
                $query->whereRaw('0=1');
            }
        }
        // Admin Pusat / Super Admin see all
        return $query;
    }

    protected function baseUsersQuery(User $user): Builder
    {
        $roleName = $this->roleName($user);
        $query = User::query()->with(['role', 'linkedMember.unit', 'organizationUnit']);

        if ($roleName === 'admin_unit') {
            $unitId = $user->currentUnitId();
            if (!$unitId) {
                return $query->whereRaw('0=1');
            }
            $query->where(function ($q) use ($unitId) {
                $q->where('organization_unit_id', $unitId)
                    ->orWhereHas('linkedMember', fn($m) => $m->where('organization_unit_id', $unitId));
            });
        }

        return $query;
    }

    protected function baseFinanceLedgersQuery(User $user): Builder
    {
        $query = FinanceLedger::query()->with(['category', 'organizationUnit']);

        if (!$this->featureEnabled('finance')) {
            return $query->whereRaw('0=1');
        }

        if ($user->hasGlobalAccess()) {
            return $query;
        }

        $unitId = $user->currentUnitId();
        if (!$unitId) {
            return $query->whereRaw('0=1');
        }

        return $query->where('organization_unit_id', $unitId);
    }

    // --- Search & Paginate Implementations ---

    protected function searchAnnouncements(User $user, string $q, int $limit): Collection
    {
        return $this->baseAnnouncementsQuery($user)
            ->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                    ->orWhere('body', 'like', "%{$q}%");
            })
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn($item) => $this->mapAnnouncement($item, $user));
    }

    protected function paginateAnnouncements(User $user, string $q, int $perPage)
    {
        $paginator = $this->baseAnnouncementsQuery($user)
            ->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                    ->orWhere('body', 'like', "%{$q}%");
            })
            ->latest()
            ->paginate($perPage);

        $paginator->getCollection()->transform(fn($item) => $this->mapAnnouncement($item, $user));
        return $paginator;
    }

    protected function searchLetters(User $user, string $q, int $limit): Collection
    {
        return $this->baseLettersQuery($user)
            ->where(function ($query) use ($q) {
                $query->where('subject', 'like', "%{$q}%")
                    ->orWhere('letter_number', 'like', "%{$q}%");
            })
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn($item) => $this->mapLetter($item));
    }

    protected function paginateLetters(User $user, string $q, int $perPage)
    {
        $paginator = $this->baseLettersQuery($user)
            ->where(function ($query) use ($q) {
                $query->where('subject', 'like', "%{$q}%")
                    ->orWhere('letter_number', 'like', "%{$q}%");
            })
            ->latest()
            ->paginate($perPage);

        $paginator->getCollection()->transform(fn($item) => $this->mapLetter($item));
        return $paginator;
    }

    protected function searchAspirations(User $user, string $q, int $limit): Collection
    {
        return $this->baseAspirationsQuery($user)
            ->where('title', 'like', "%{$q}%")
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn($item) => $this->mapAspiration($item, $user));
    }

    protected function paginateAspirations(User $user, string $q, int $perPage)
    {
        $paginator = $this->baseAspirationsQuery($user)
            ->where('title', 'like', "%{$q}%")
            ->latest()
            ->paginate($perPage);

        $paginator->getCollection()->transform(fn($item) => $this->mapAspiration($item, $user));
        return $paginator;
    }

    protected function searchMembers(User $user, string $q, int $limit): Collection
    {
        return $this->baseMembersQuery($user)
            ->where(function ($qBuilder) use ($q) {
                $qBuilder->where('full_name', 'like', "%{$q}%") // Corrected column
                    ->orWhere('kta_number', 'like', "%{$q}%");
            })
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn($item) => $this->mapMember($item, $user));
    }

    protected function paginateMembers(User $user, string $q, int $perPage)
    {
        $paginator = $this->baseMembersQuery($user)
            ->where(function ($qBuilder) use ($q) {
                $qBuilder->where('full_name', 'like', "%{$q}%") // Corrected column
                    ->orWhere('kta_number', 'like', "%{$q}%");
            })
            ->latest()
            ->paginate($perPage);

        $paginator->getCollection()->transform(fn($item) => $this->mapMember($item, $user));
        return $paginator;
    }

    protected function searchUsers(User $user, string $q, int $limit): Collection
    {
        return $this->baseUsersQuery($user)
            ->where(function ($qb) use ($q) {
                $qb->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhereHas('linkedMember', fn($m) => $m->where('full_name', 'like', "%{$q}%"));
            })
            ->orderByDesc('id')
            ->take($limit)
            ->get()
            ->map(fn($item) => $this->mapUser($item, $user));
    }

    protected function paginateUsers(User $user, string $q, int $perPage)
    {
        $paginator = $this->baseUsersQuery($user)
            ->where(function ($qb) use ($q) {
                $qb->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhereHas('linkedMember', fn($m) => $m->where('full_name', 'like', "%{$q}%"));
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        $paginator->getCollection()->transform(fn($item) => $this->mapUser($item, $user));
        return $paginator;
    }

    protected function searchDuesPayments(User $user, string $q, int $limit): Collection
    {
        if (!$this->featureEnabled('finance')) {
            return collect();
        }

        $roleName = $this->roleName($user);
        $unitId = $user->currentUnitId();
        $period = now()->format('Y-m');

        $query = Member::query()
            ->select([
                'members.id',
                'members.full_name',
                'members.kta_number',
                'members.organization_unit_id',
                'ou.name as unit_name',
                'dp.status as dues_status',
                'dp.amount as dues_amount',
                'dp.paid_at as dues_paid_at',
            ])
            ->join('organization_units as ou', 'ou.id', '=', 'members.organization_unit_id')
            ->leftJoin('dues_payments as dp', function ($join) use ($period) {
                $join->on('members.id', '=', 'dp.member_id')
                    ->where('dp.period', '=', $period);
            })
            ->where('members.status', 'aktif');

        if (in_array($roleName, ['bendahara', 'admin_unit'])) {
            if (!$unitId) {
                return collect();
            }
            $query->where('members.organization_unit_id', $unitId);
        }

        $query->where(function ($qb) use ($q) {
            $qb->where('members.full_name', 'like', "%{$q}%")
                ->orWhere('members.kta_number', 'like', "%{$q}%");
        });

        return $query
            ->orderBy('members.full_name')
            ->take($limit)
            ->get()
            ->map(fn($row) => $this->mapDuesPaymentRow($row, $period));
    }

    protected function paginateDuesPayments(User $user, string $q, int $perPage)
    {
        if (!$this->featureEnabled('finance')) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        $roleName = $this->roleName($user);
        $unitId = $user->currentUnitId();
        $period = now()->format('Y-m');

        $query = Member::query()
            ->select([
                'members.id',
                'members.full_name',
                'members.kta_number',
                'members.organization_unit_id',
                'ou.name as unit_name',
                'dp.status as dues_status',
                'dp.amount as dues_amount',
                'dp.paid_at as dues_paid_at',
            ])
            ->join('organization_units as ou', 'ou.id', '=', 'members.organization_unit_id')
            ->leftJoin('dues_payments as dp', function ($join) use ($period) {
                $join->on('members.id', '=', 'dp.member_id')
                    ->where('dp.period', '=', $period);
            })
            ->where('members.status', 'aktif');

        if (in_array($roleName, ['bendahara', 'admin_unit'])) {
            if (!$unitId) {
                return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
            }
            $query->where('members.organization_unit_id', $unitId);
        }

        $query->where(function ($qb) use ($q) {
            $qb->where('members.full_name', 'like', "%{$q}%")
                ->orWhere('members.kta_number', 'like', "%{$q}%");
        });

        $paginator = $query->orderBy('members.full_name')->paginate($perPage);
        $paginator->getCollection()->transform(fn($row) => $this->mapDuesPaymentRow($row, $period));
        return $paginator;
    }

    protected function searchFinanceLedgers(User $user, string $q, int $limit): Collection
    {
        if (!$this->featureEnabled('finance')) {
            return collect();
        }

        return $this->baseFinanceLedgersQuery($user)
            ->where(function ($qb) use ($q) {
                $qb->where('description', 'like', "%{$q}%")
                    ->orWhereHas('category', fn($c) => $c->where('name', 'like', "%{$q}%"));
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->take($limit)
            ->get()
            ->map(fn($item) => $this->mapFinanceLedger($item, $user));
    }

    protected function paginateFinanceLedgers(User $user, string $q, int $perPage)
    {
        if (!$this->featureEnabled('finance')) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        $paginator = $this->baseFinanceLedgersQuery($user)
            ->where(function ($qb) use ($q) {
                $qb->where('description', 'like', "%{$q}%")
                    ->orWhereHas('category', fn($c) => $c->where('name', 'like', "%{$q}%"));
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($perPage);

        $paginator->getCollection()->transform(fn($item) => $this->mapFinanceLedger($item, $user));
        return $paginator;
    }

    // --- Mappers & Helpers ---

    protected function mapAnnouncement($item, User $user)
    {
        $url = route('announcements.show', $item->id);
        if ($user->can('update', $item)) {
            $url = route('admin.announcements.edit', $item->id);
        }

        return [
            'id' => $item->id,
            'type' => 'announcement',
            'title' => $item->title,
            'snippet' => Str::limit(strip_tags($item->body), 100),
            'url' => $url,
            'meta' => [
                'date' => $item->created_at->diffForHumans(),
            ],
        ];
    }

    protected function mapLetter($item)
    {
        return [
            'id' => $item->id,
            'type' => 'letter',
            'title' => $item->subject,
            'snippet' => $item->letter_number ?? 'No Number',
            'url' => route('letters.show', $item->id),
            'meta' => [
                'status' => $item->status,
                'date' => $item->created_at->format('d M Y'),
                'from' => $item->fromUnit?->name ?? '-',
            ],
        ];
    }

    protected function mapAspiration($item, User $user)
    {
        $roleName = $this->roleName($user);
        $baseRoute = $roleName === 'anggota' ? 'member.aspirations.show' : 'admin.aspirations.show';
        return [
            'id' => $item->id,
            'type' => 'aspiration',
            'title' => $item->title,
            'snippet' => Str::limit(strip_tags($item->body), 100),
            'url' => route($baseRoute, $item->id),
            'meta' => [
                'status' => $item->status,
                'date' => $item->created_at->format('d M Y'),
            ],
        ];
    }

    protected function mapMember($item, User $user)
    {
        // PII Masking applied here if needed?
        // Current requirement: "admin_pusat/super_admin: boleh email masked"
        // "anggota/bendahara/admin_unit: full_name, kta_number only"

        $roleName = $this->roleName($user);
        $isGlobalAdmin = in_array($roleName, ['admin_pusat', 'super_admin']);

        $meta = ['unit' => $item->unit?->name];
        if ($isGlobalAdmin && $item->email) {
            $meta['email'] = $this->maskPii($item->email, 'email');
        }

        return [
            'id' => $item->id,
            'type' => 'member',
            'title' => $item->full_name, // Corrected from name
            'snippet' => $item->kta_number,
            'url' => route('admin.members.show', $item->id),
            'meta' => $meta,
        ];
    }

    protected function mapUser(User $item, User $actor)
    {
        $unitName = $item->organizationUnit?->name ?? $item->linkedMember?->unit?->name;
        $meta = [
            'role' => $item->role?->label ?? ($item->role?->name ?? '-'),
        ];
        if ($unitName) {
            $meta['unit'] = $unitName;
        }
        if ($item->email) {
            $meta['email'] = $this->maskPii($item->email, 'email');
        }

        return [
            'id' => $item->id,
            'type' => 'user',
            'title' => $item->name,
            'snippet' => $item->linkedMember?->full_name ?? '',
            'url' => route('admin.users.show', $item->id),
            'meta' => $meta,
        ];
    }

    protected function mapDuesPaymentRow($row, string $period): array
    {
        $status = $row->dues_status ?: 'unpaid';

        return [
            'id' => $row->id,
            'type' => 'dues_payment',
            'title' => $row->full_name,
            'snippet' => trim(($row->kta_number ? $row->kta_number . ' • ' : '') . strtoupper($status)),
            'url' => route('finance.dues.index', ['period' => $period, 'search' => $row->kta_number ?: $row->full_name]),
            'meta' => [
                'period' => $period,
                'unit' => $row->unit_name ?? '-',
            ],
        ];
    }

    protected function mapFinanceLedger(FinanceLedger $item, User $actor): array
    {
        $amount = is_numeric($item->amount) ? (float) $item->amount : (float) str_replace(',', '.', (string) $item->amount);
        $amountLabel = 'Rp ' . number_format($amount, 0, ',', '.');
        $categoryLabel = $item->category?->name ?? '-';
        $typeLabel = $item->type === 'income' ? 'Pemasukan' : 'Pengeluaran';

        $url = $actor->can('update', $item)
            ? route('finance.ledgers.edit', $item->id)
            : route('finance.ledgers.index', ['focus' => $item->id]);

        return [
            'id' => $item->id,
            'type' => 'finance_ledger',
            'title' => "{$typeLabel}: {$categoryLabel}",
            'snippet' => trim($amountLabel . ($item->description ? ' • ' . Str::limit($item->description, 60) : '')),
            'url' => $url,
            'meta' => [
                'status' => $item->status,
                'date' => $item->date?->format('d M Y') ?? '-',
            ],
        ];
    }

    protected function maskPii($value, $type)
    {
        if (!$value)
            return $value;
        if ($type === 'email') {
            // Simple masking: f***@domain.com
            $parts = explode('@', $value);
            if (count($parts) < 2)
                return $value;
            $name = $parts[0];
            $domain = $parts[1];
            $len = strlen($name);
            $visible = min(1, ceil($len / 4));
            return substr($name, 0, $visible) . str_repeat('*', 3) . '@' . $domain;
        }
        if ($type === 'phone' || $type === 'nip') {
            // Show last 3 only
            $len = strlen($value);
            if ($len <= 4)
                return $value;
            return str_repeat('*', $len - 3) . substr($value, -3);
        }
        return $value;
    }

    protected function getAllowedTypes(User $user): array
    {
        $role = $this->roleName($user);
        $types = [];

        if ($this->featureEnabled('announcements')) {
            $types[] = 'announcements';
        }

        if ($this->featureEnabled('letters')) {
            $types[] = 'letters';
        }

        // Aspirations currently have no feature flag
        $types[] = 'aspirations';

        if (in_array($role, ['admin_unit', 'admin_pusat', 'super_admin', 'bendahara'])) {
            $types[] = 'members';
        }

        if (in_array($role, ['admin_unit', 'admin_pusat', 'super_admin'])) {
            $types[] = 'users';
        }

        if ($this->featureEnabled('finance') && in_array($role, ['admin_unit', 'bendahara', 'super_admin'])) {
            $types[] = 'dues_payments';
            $types[] = 'finance_ledgers';
        }

        return $types;
    }

    protected function roleName(User $user): string
    {
        return $user->role?->name ?? 'anggota';
    }

    protected function featureEnabled(string $feature): bool
    {
        return (bool) config("features.{$feature}", true);
    }
}
