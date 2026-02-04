<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'google_id',
        'avatar',
        'member_id',
        'organization_unit_id',
        'microsoft_id',
        'company_email',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function canAccessRole($roleName)
    {
        return $this->role && $this->role->name === $roleName;
    }

    public function hasRole($roles): bool
    {
        if (is_array($roles)) {
            return $this->role && in_array($this->role->name, $roles);
        }

        return $this->role && $this->role->name === $roles;
    }

    public function notificationPreference()
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function announcementDismissals()
    {
        return $this->hasMany(AnnouncementDismissal::class);
    }

    public function member()
    {
        return $this->hasOne(Member::class);
    }

    public function organizationUnit()
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    public function assignMember(Member $member): void
    {
        $this->member_id = $member->id;

        // FIX: SELALU update member.user_id ke user yang sedang login
        // Ini memungkinkan user Gmail mengambil alih dari User PLN
        // Mencegah bug yang menyebabkan user->member return NULL
        $member->user_id = $this->id;
        $member->save();

        if (! $this->role || $this->role->name === 'reguler') {
            $role = \App\Models\Role::where('name', 'anggota')->first();
            if ($role) {
                $this->role_id = $role->id;
            }
        }
        $this->save();
    }

    /**
     * Check if user has global data access (can see all units).
     * Super admin and admin pusat have global access even if they have a unit.
     */
    public function hasGlobalAccess(): bool
    {
        return $this->hasRole(['super_admin', 'admin_pusat']);
    }

    /**
     * Get the user's effective organization unit ID.
     * Source of truth for unit scoping in policies and queries.
     * Priority: user.organization_unit_id > member.organization_unit_id
     */
    public function currentUnitId(): ?int
    {
        if ($this->organization_unit_id) {
            return (int) $this->organization_unit_id;
        }

        // Fallback to member's unit
        $member = $this->member_id ? Member::find($this->member_id) : null;

        return $member?->organization_unit_id ? (int) $member->organization_unit_id : null;
    }

    /**
     * Get the member linked to this user via member_id.
     */
    public function linkedMember()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * Check if user can approve letters with given signer type.
     * User must have a linked member with matching union position.
     */
    public function canApproveSignerType(string $signerType): bool
    {
        if (! $this->member_id) {
            return false;
        }

        $member = Member::with('unionPosition')->find($this->member_id);
        if (! $member?->unionPosition) {
            return false;
        }

        $positionName = strtolower($member->unionPosition->name);

        return $positionName === strtolower($signerType);
    }

    /**
     * Get the union position name of the linked member.
     */
    public function getUnionPositionName(): ?string
    {
        if (! $this->member_id) {
            return null;
        }

        $member = Member::with('unionPosition')->find($this->member_id);

        return $member?->unionPosition?->name;
    }

    /**
     * Check if user is an "Officer/Pengurus" based on union_position.
     *
     * An officer is someone whose union_position.name is NOT "Anggota" (case-insensitive).
     * Super admin and admin_pusat always return true for operational/verification purposes.
     */
    public function isOfficer(): bool
    {
        // Global admins can always view officer content for operational purposes
        if ($this->hasRole(['super_admin', 'admin_pusat'])) {
            return true;
        }

        $positionName = $this->getUnionPositionName();

        // If no union position, not an officer
        if (! $positionName) {
            return false;
        }

        // Officer = any position except "Anggota"
        return strtolower(trim($positionName)) !== 'anggota';
    }

    /**
     * Get the organization this user manages (for admin_pusat & bendahara_pusat).
     * Returns DPP organization for global admins, or user's own unit for others.
     */
    public function getManagedOrganizationAttribute()
    {
        if ($this->hasRole(['admin_pusat', 'bendahara_pusat'])) {
            return OrganizationUnit::where('is_pusat', true)->first();
        }

        return $this->organizationUnit;
    }

    /**
     * Check if user can view global scope (all units).
     */
    public function canViewGlobalScope(): bool
    {
        return $this->hasRole(['super_admin', 'admin_pusat', 'bendahara_pusat']);
    }

    /**
     * Check if user can manage/edit specific organization's data.
     */
    public function canManageOrganization(OrganizationUnit $org): bool
    {
        // Super admin can manage all
        if ($this->hasRole('super_admin')) {
            return true;
        }

        // admin_pusat & bendahara_pusat can only manage DPP
        if ($this->hasRole(['admin_pusat', 'bendahara_pusat'])) {
            return $org->is_pusat;
        }

        // admin_unit can manage their own unit
        if ($this->hasRole('admin_unit')) {
            return $this->organization_unit_id === $org->id;
        }

        return false;
    }
}
