<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $organization_unit_id
 * @property int $finance_category_id
 * @property string $type
 * @property float $amount
 * @property \Illuminate\Support\Carbon $date
 * @property string|null $description
 * @property string|null $attachment_path
 * @property string $status
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property string|null $rejected_reason
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\FinanceCategory $category
 * @property-read \App\Models\OrganizationUnit $organizationUnit
 * @property-read \App\Models\User $creator
 * @property-read \App\Models\User $approvedBy
 */
class FinanceLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_unit_id',
        'finance_category_id',
        'type',
        'amount',
        'date',
        'description',
        'attachment_path',
        'status',
        'approved_by',
        'approved_at',
        'rejected_reason',
        'submitted_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'approved_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(FinanceCategory::class, 'finance_category_id');
    }

    public function organizationUnit()
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if workflow is enabled
     */
    public static function workflowEnabled(): bool
    {
        return (bool) env('FINANCE_WORKFLOW_ENABLED', false);
    }

    /**
     * Get default status based on workflow setting
     */
    public static function defaultStatus(): string
    {
        return static::workflowEnabled() ? 'submitted' : 'approved';
    }

    /**
     * Check if ledger can be edited
     */
    public function canBeEdited(): bool
    {
        if (!static::workflowEnabled()) {
            return true;
        }
        return in_array($this->status, ['draft', 'submitted']);
    }

    /**
     * Check if ledger can be deleted
     */
    public function canBeDeleted(): bool
    {
        return $this->canBeEdited();
    }
}
