<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'label','description','domain_whitelist','default_permissions'];

    protected $casts = [
        'domain_whitelist' => 'array',
        'default_permissions' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
