<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LetterCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'color',
        'sort_order',
        'is_active',
        'template_subject',
        'template_body',
        'template_cc_text',
        'default_confidentiality',
        'default_urgency',
        'default_signer_type',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Check if category has a template.
     */
    public function hasTemplate(): bool
    {
        return $this->template_subject || $this->template_body;
    }

    /**
     * Get all letters in this category.
     */
    public function letters()
    {
        return $this->hasMany(Letter::class);
    }

    /**
     * Scope to only active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('code');
    }
}
