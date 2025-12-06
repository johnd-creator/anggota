<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'address',
    ];

    public function members()
    {
        return $this->hasMany(Member::class, 'organization_unit_id');
    }
}
