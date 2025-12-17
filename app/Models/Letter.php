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
        'to_type',
        'to_unit_id',
        'to_member_id',
        'to_external_name',
        'to_external_org',
        'subject',
        'body',
        'cc_text',
        'confidentiality',
        'urgency',
        'status',
        'submitted_at',
        'approved_by_user_id',
        'approved_at',
        'rejected_by_user_id',
        'rejected_at',
        'revision_note',
        'month',
        'year',
        'sequence',
        'letter_number',
        'verification_token',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'sequence' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

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
