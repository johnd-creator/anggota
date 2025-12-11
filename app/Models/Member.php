<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;

/**
 * @property \Illuminate\Support\Carbon|null $company_join_date
 */
class Member extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'full_name',
        'employee_id',
        'email',
        'phone',
        'birth_place',
        'birth_date',
        'gender',
        'company_join_date',
        'address',
        'emergency_contact',
        'job_title',
        'employment_type',
        'status',
        'join_date',
        'organization_unit_id',
        'nra',
        'join_year',
        'sequence_number',
        'photo_path',
        'documents',
        'notes',
        'kta_number',
        'nip',
        'union_position_id'
    ];

    protected $casts = [
        'birth_date' => 'date',
        'join_date' => 'date',
        'company_join_date' => 'date',
        'documents' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function unit()
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    public function documents()
    {
        return $this->hasMany(MemberDocument::class);
    }

    public function statusLogs()
    {
        return $this->hasMany(MemberStatusLog::class);
    }

    // nik removed from schema

    public function unionPosition()
    {
        return $this->belongsTo(UnionPosition::class, 'union_position_id');
    }

    protected function address(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (!$value)
                    return $value;
                try {
                    return Crypt::decryptString($value);
                } catch (\Throwable $e) {
                    return $value;
                }
            },
            set: fn($value) => $value ? Crypt::encryptString($value) : $value,
        );
    }
}
