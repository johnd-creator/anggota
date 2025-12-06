<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberDocument extends Model
{
    use HasFactory;

    protected $fillable = ['member_id','type','path','original_name','size'];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}

