<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberUpdateRequest extends Model
{
    use HasFactory;

    protected $fillable = ['member_id','old_data','new_data','status','reviewer_id','notes'];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}

