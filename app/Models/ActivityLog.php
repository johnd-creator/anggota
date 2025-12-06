<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = ['actor_id','action','subject_type','subject_id','payload'];

    protected $casts = [
        'payload' => 'array',
    ];
}

