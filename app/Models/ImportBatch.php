<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'actor_user_id',
        'organization_unit_id',
        'status',
        'original_filename',
        'stored_path',
        'file_hash',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'created_count',
        'updated_count',
        'started_at',
        'finished_at',
        'committed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'committed_at' => 'datetime',
        'total_rows' => 'integer',
        'valid_rows' => 'integer',
        'invalid_rows' => 'integer',
        'created_count' => 'integer',
        'updated_count' => 'integer',
    ];

    /**
     * The user who initiated the import.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * The organization unit for the import.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    /**
     * Errors found during validation.
     */
    public function errors(): HasMany
    {
        return $this->hasMany(ImportBatchError::class);
    }

    /**
     * Scope to filter by unit.
     */
    public function scopeForUnit($query, ?int $unitId)
    {
        if ($unitId) {
            return $query->where('organization_unit_id', $unitId);
        }
        return $query;
    }

    /**
     * Mark batch as previewed with counts.
     */
    public function markPreviewed(int $total, int $valid, int $invalid): void
    {
        $this->update([
            'status' => 'previewed',
            'total_rows' => $total,
            'valid_rows' => $valid,
            'invalid_rows' => $invalid,
        ]);
    }

    /**
     * Mark batch as processing.
     */
    public function markProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark batch as completed.
     */
    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'finished_at' => now(),
        ]);
    }

    /**
     * Mark batch as failed.
     */
    public function markFailed(): void
    {
        $this->update([
            'status' => 'failed',
            'finished_at' => now(),
        ]);
    }
}
