<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = ['user_id','channels','digest_daily'];
    protected $casts = ['channels' => 'array', 'digest_daily' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
