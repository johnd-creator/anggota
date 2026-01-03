<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'channels', 'digest_daily'];
    protected $casts = ['channels' => 'array', 'digest_daily' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if a specific channel is enabled.
     * Defaults to true if not set (opt-out model).
     */
    public function hasChannelEnabled(string $channel): bool
    {
        if ($this->channels === null) {
            return true; // Default enabled if no preferences set
        }

        return $this->channels[$channel] ?? true;
    }

    /**
     * Static helper to check if a user has a channel enabled.
     */
    public static function isChannelEnabled(int $userId, string $channel): bool
    {
        $pref = static::where('user_id', $userId)->first();

        if (!$pref) {
            return true; // Default enabled if no preferences record
        }

        return $pref->hasChannelEnabled($channel);
    }
}
