<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event',
        'ip_address',
        'user_agent',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['role'] ?? null, function ($query, $role) {
            $query->whereHas('user.role', function ($q) use ($role) {
                $q->where('name', $role);
            });
        })->when($filters['date_start'] ?? null, function ($query, $date) {
            $query->whereDate('created_at', '>=', $date);
        })->when($filters['date_end'] ?? null, function ($query, $date) {
            $query->whereDate('created_at', '<=', $date);
        });
    }
}
