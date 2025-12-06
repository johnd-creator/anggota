<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnionPosition extends Model
{
    use HasFactory;

    protected $fillable = ['name','code','description'];

    public function members()
    {
        return $this->hasMany(Member::class, 'union_position_id');
    }
}

