<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendingMember extends Model
{
    use HasFactory;

    protected $fillable = ['user_id','email','name','organization_unit_id','notes','status','reviewer_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function unit()
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }
}

