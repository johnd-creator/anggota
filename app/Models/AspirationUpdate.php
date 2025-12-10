<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AspirationUpdate extends Model
{
    public $timestamps = false;

    protected $fillable = ['aspiration_id', 'user_id', 'old_status', 'new_status', 'notes'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->created_at = now();
        });
    }

    public function aspiration()
    {
        return $this->belongsTo(Aspiration::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
