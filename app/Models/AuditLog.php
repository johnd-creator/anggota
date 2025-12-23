<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        // Request context
        'request_id',
        'session_id',
        // Actor and tenant
        'user_id',
        'organization_unit_id',
        // Event details
        'event',
        'event_category',
        // HTTP context
        'route_name',
        'http_method',
        'url_path',
        'status_code',
        'duration_ms',
        // Subject (entity)
        'subject_type',
        'subject_id',
        // Client info
        'ip_address',
        'user_agent',
        // Data
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'status_code' => 'integer',
        'duration_ms' => 'integer',
        'subject_id' => 'integer',
        'organization_unit_id' => 'integer',
    ];

    /**
     * Get the user who triggered this event.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the organization unit this event belongs to.
     */
    public function organizationUnit()
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    /**
     * Get the subject model (polymorphic).
     * Returns MorphTo relationship for proper eager loading with with('subject').
     */
    public function subject()
    {
        return $this->morphTo('subject', 'subject_type', 'subject_id');
    }

    /**
     * Scope: Filter by role, date range, category, event, unit.
     */
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
        })->when($filters['category'] ?? null, function ($query, $category) {
            $query->where('event_category', $category);
        })->when($filters['event'] ?? null, function ($query, $event) {
            $query->where('event', 'like', $event . '%');
        })->when($filters['unit_id'] ?? null, function ($query, $unitId) {
            $query->where('organization_unit_id', $unitId);
        })->when($filters['user_id'] ?? null, function ($query, $userId) {
            $query->where('user_id', $userId);
        })->when($filters['request_id'] ?? null, function ($query, $requestId) {
            $query->where('request_id', $requestId);
        });
    }

    /**
     * Scope: Only events for a specific organization unit.
     */
    public function scopeForUnit($query, int $unitId)
    {
        return $query->where('organization_unit_id', $unitId);
    }

    /**
     * Scope: Only events in a specific category.
     */
    public function scopeInCategory($query, string $category)
    {
        return $query->where('event_category', $category);
    }

    /**
     * Scope: Exclude auth events (for admin_unit restricted view).
     */
    public function scopeExcludeAuth($query)
    {
        return $query->where('event_category', '!=', 'auth');
    }

    /**
     * Scope: Events older than a given date.
     */
    public function scopeOlderThan($query, $date)
    {
        return $query->where('created_at', '<', $date);
    }
}
