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

    public function hasRole($roles)
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
        if (!$member->user_id) {
            $member->user_id = $this->id;
            $member->save();
        }
        if (!$this->role || $this->role->name === 'reguler') {
            $role = \App\Models\Role::where('name', 'anggota')->first();
            if ($role)
                $this->role_id = $role->id;
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
}
