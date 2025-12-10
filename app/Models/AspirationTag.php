<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AspirationTag extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function aspirations()
    {
        return $this->belongsToMany(Aspiration::class, 'aspiration_tag', 'tag_id', 'aspiration_id');
    }
}
