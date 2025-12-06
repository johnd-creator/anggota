<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id','type','notifiable_type','notifiable_id','message','data','read_at'];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];
}

