<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LetterAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'letter_id',
        'original_name',
        'path',
        'mime',
        'size',
        'uploaded_by_user_id',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function letter()
    {
        return $this->belongsTo(Letter::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
