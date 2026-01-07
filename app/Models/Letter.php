<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Letter extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'creator_user_id',
        'from_unit_id',
        'letter_category_id',
        'signer_type',
        'signer_type_secondary',
        'to_type',
        'to_unit_id',
        'to_member_id',
        'to_external_name',
        'to_external_org',
        'to_external_address',
        'subject',
        'body',
        'cc_text',
        'confidentiality',
        'urgency',
        'status',
        'submitted_at',
        'approved_by_user_id',
        'approved_at',
        'approved_primary_at',
        'approved_secondary_by_user_id',
        'approved_secondary_at',
        'rejected_by_user_id',
        'rejected_at',
        'revision_note',
        'month',
        'year',
        'sequence',
        'letter_number',
        'verification_token',
        'sla_due_at',
        'sla_status',
        'sla_marked_at',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'sequence' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'approved_primary_at' => 'datetime',
        'approved_secondary_at' => 'datetime',
        'rejected_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'sla_marked_at' => 'datetime',
    ];

    protected $appends = ['is_overdue', 'age_hours'];

    /**
     * Get SLA hours based on urgency level.
     */
    public static function getSlaHours(string $urgency): int
    {
        return config("letters.sla_hours_by_urgency.{$urgency}", config('letters.default_sla_hours', 72));
    }

    /**
     * Calculate and set SLA due date based on urgency.
     */
    public function calculateSlaDueAt(): void
    {
        if ($this->submitted_at && $this->urgency) {
            $hours = self::getSlaHours($this->urgency);
            $this->sla_due_at = $this->submitted_at->copy()->addHours($hours);
            $this->sla_status = 'ok';
        }
    }

    /**
     * Check if the letter is overdue.
     */
    public function getIsOverdueAttribute(): bool
    {
        if (!$this->sla_due_at || $this->status !== 'submitted') {
            return false;
        }
        return now()->greaterThan($this->sla_due_at);
    }

    /**
     * Get age in hours since submission.
     */
    public function getAgeHoursAttribute(): ?int
    {
        if (!$this->submitted_at) {
            return null;
        }
        return (int) $this->submitted_at->diffInHours(now());
    }

    /**
     * Get remaining hours until SLA breach.
     */
    public function getRemainingHoursAttribute(): ?int
    {
        if (!$this->sla_due_at) {
            return null;
        }
        $diff = now()->diffInHours($this->sla_due_at, false);
        return max(0, (int) $diff);
    }

    /**
     * Mark as SLA breached.
     */
    public function markSlaBreach(): void
    {
        $this->sla_status = 'breach';
        $this->sla_marked_at = now();
        $this->save();
    }

    /**
     * Get the user who created this letter.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    /**
     * Get the unit this letter is from.
     */
    public function fromUnit()
    {
        return $this->belongsTo(OrganizationUnit::class, 'from_unit_id');
    }

    /**
     * Get the category of this letter.
     */
    public function category()
    {
        return $this->belongsTo(LetterCategory::class, 'letter_category_id');
    }

    /**
     * Get the destination unit (if to_type is 'unit').
     */
    public function toUnit()
    {
        return $this->belongsTo(OrganizationUnit::class, 'to_unit_id');
    }

    /**
     * Get the destination member (if to_type is 'member').
     */
    public function toMember()
    {
        return $this->belongsTo(Member::class, 'to_member_id');
    }

    /**
     * Get the user who approved this letter.
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * Get the user who approved this letter (secondary approver).
     */
    public function approvedSecondaryBy()
    {
        return $this->belongsTo(User::class, 'approved_secondary_by_user_id');
    }

    /**
     * Check if this letter requires secondary (dual) approval.
     */
    public function requiresSecondaryApproval(): bool
    {
        return !is_null($this->signer_type_secondary);
    }

    /**
     * Check if primary approval has been completed.
     */
    public function isPrimaryApproved(): bool
    {
        return !is_null($this->approved_by_user_id);
    }

    /**
     * Check if secondary approval has been completed.
     */
    public function isSecondaryApproved(): bool
    {
        return !is_null($this->approved_secondary_by_user_id);
    }

    /**
     * Get the user who rejected this letter.
     */
    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    /**
     * Get the revision history.
     */
    public function revisions()
    {
        return $this->hasMany(LetterRevision::class)->orderBy('created_at', 'desc');
    }

    public function reads()
    {
        return $this->hasMany(LetterRead::class);
    }

    public function attachments()
    {
        return $this->hasMany(LetterAttachment::class);
    }

    /**
     * Scope for draft letters.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope for sent letters.
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope for letters needing approval.
     */
    public function scopeNeedsApproval($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeUnreadFor($query, User $user)
    {
        return $query->whereDoesntHave('reads', fn($q) => $q->where('user_id', $user->id));
    }
}
