<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatchError extends Model
{
    protected $fillable = [
        'import_batch_id',
        'row_number',
        'errors_json',
    ];

    protected $casts = [
        'row_number' => 'integer',
        'errors_json' => 'array',
    ];

    /**
     * The batch this error belongs to.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
