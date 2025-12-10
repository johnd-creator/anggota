<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AspirationSupport extends Model
{
    public $timestamps = false;

    protected $fillable = ['aspiration_id', 'member_id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted()
    {
        // Auto-set created_at on create
        static::creating(function ($model) {
            $model->created_at = now();
        });
    }

    public function aspiration()
    {
        return $this->belongsTo(Aspiration::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
