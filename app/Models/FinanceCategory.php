<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_unit_id',
        'name',
        'type',
        'description',
        'is_recurring',
        'default_amount',
        'is_system',
        'created_by',
    ];

    protected $casts = [
        'is_recurring' => 'boolean',
        'default_amount' => 'decimal:2',
        'is_system' => 'boolean',
    ];

    public function organizationUnit()
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    public function ledgers()
    {
        return $this->hasMany(FinanceLedger::class, 'finance_category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    public function scopeForUnit($query, $unitId = null)
    {
        return $query->where(function ($q) use ($unitId) {
            $q->whereNull('organization_unit_id'); // Global categories
            if ($unitId) {
                $q->orWhere('organization_unit_id', $unitId);
            }
        });
    }
}
