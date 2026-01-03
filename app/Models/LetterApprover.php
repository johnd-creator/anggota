<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LetterApprover extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_unit_id',
        'signer_type',
        'user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the organization unit.
     */
    public function organizationUnit()
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    /**
     * Get the user (approver).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for active approvers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for specific unit.
     */
    public function scopeForUnit($query, ?int $unitId)
    {
        if ($unitId === null) {
            return $query->whereNull('organization_unit_id');
        }
        return $query->where('organization_unit_id', $unitId);
    }

    /**
     * Scope for specific signer type.
     */
    public function scopeForSignerType($query, string $signerType)
    {
        return $query->where('signer_type', strtolower($signerType));
    }

    /**
     * Check if a user is an active approver for a given unit and signer type.
     */
    public static function isApprover(?int $unitId, string $signerType, int $userId): bool
    {
        return static::active()
            ->forUnit($unitId)
            ->forSignerType($signerType)
            ->where('user_id', $userId)
            ->exists();
    }
}
