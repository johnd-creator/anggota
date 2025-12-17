<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LetterRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'letter_id',
        'actor_user_id',
        'note',
    ];

    /**
     * Get the letter this revision belongs to.
     */
    public function letter()
    {
        return $this->belongsTo(Letter::class);
    }

    /**
     * Get the user who made this revision.
     */
    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
