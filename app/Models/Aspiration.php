<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aspiration extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'organization_unit_id',
        'category_id',
        'title',
        'body',
        'status',
        'merged_into_id',
        'support_count',
        'user_id',
    ];

    protected $casts = [
        'support_count' => 'integer',
    ];

    // Relationships
    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function unit()
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    public function category()
    {
        return $this->belongsTo(AspirationCategory::class, 'category_id');
    }

    public function mergedInto()
    {
        return $this->belongsTo(Aspiration::class, 'merged_into_id');
    }

    public function mergedFrom()
    {
        return $this->hasMany(Aspiration::class, 'merged_into_id');
    }

    public function supports()
    {
        return $this->hasMany(AspirationSupport::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supporters()
    {
        return $this->belongsToMany(Member::class, 'aspiration_supports', 'aspiration_id', 'member_id')
            ->withPivot('created_at');
    }

    public function tags()
    {
        return $this->belongsToMany(AspirationTag::class, 'aspiration_tag', 'aspiration_id', 'tag_id');
    }

    public function updates()
    {
        return $this->hasMany(AspirationUpdate::class)->orderByDesc('created_at');
    }

    // Helpers
    public function isMerged(): bool
    {
        return !is_null($this->merged_into_id);
    }

    public function isSupporter(Member $member): bool
    {
        return $this->supports()->where('member_id', $member->id)->exists();
    }

    public function incrementSupport(): void
    {
        $this->increment('support_count');
    }

    public function decrementSupport(): void
    {
        $this->decrement('support_count');
    }

    public function recalculateSupportCount(): void
    {
        $this->support_count = $this->supports()->count();
        $this->save();
    }

    // Scopes
    public function scopeNotMerged($query)
    {
        return $query->whereNull('merged_into_id');
    }

    public function scopeByUnit($query, $unitId)
    {
        return $query->where('organization_unit_id', $unitId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
