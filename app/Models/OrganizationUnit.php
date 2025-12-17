<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'organization_type',
        'abbreviation',
        'address',
        'phone',
        'email',
        // Letterhead fields
        'letterhead_name',
        'letterhead_address',
        'letterhead_city',
        'letterhead_postal_code',
        'letterhead_phone',
        'letterhead_email',
        'letterhead_website',
        'letterhead_fax',
        'letterhead_whatsapp',
        'letterhead_footer_text',
        'letterhead_logo_path',
    ];

    public function members()
    {
        return $this->hasMany(Member::class, 'organization_unit_id');
    }
}
