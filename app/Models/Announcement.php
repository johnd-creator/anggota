<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'body',
        'scope_type',
        'organization_unit_id',
        'is_active',
        'pin_to_dashboard',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'pin_to_dashboard' => 'boolean',
    ];

    public function attachments(): HasMany
    {
        return $this->hasMany(AnnouncementAttachment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function scopeVisibleTo($query, User $user)
    {
        $unitId = $user->currentUnitId();

        return $query->where('is_active', true)
            ->where(function ($q) use ($user, $unitId) {
                // 1. Global all
                $q->orWhere('scope_type', 'global_all');

                // 2. Global officers (union_position != 'Anggota')
                if ($user->isOfficer()) {
                    $q->orWhere('scope_type', 'global_officers');
                }

                // 3. Unit scope (match user's unit)
                if ($unitId) {
                    $q->orWhere(function ($sub) use ($unitId) {
                        $sub->where('scope_type', 'unit')
                            ->where('organization_unit_id', $unitId);
                    });
                }
            });
    }

    public function scopePinned($query)
    {
        return $query->where('pin_to_dashboard', true);
    }
}
