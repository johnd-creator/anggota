<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberStatusLog extends Model
{
    use HasFactory;

    protected $fillable = ['member_id','date','old_status','new_status','old_unit_id','new_unit_id','notes'];

    protected $casts = [
        'date' => 'date',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}

