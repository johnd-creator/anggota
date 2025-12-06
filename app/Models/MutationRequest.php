<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MutationRequest extends Model
{
    use HasFactory;

    protected $fillable = ['member_id','from_unit_id','to_unit_id','effective_date','reason','document_path','status','submitted_by','approved_by'];

    protected $casts = [
        'effective_date' => 'date',
    ];

    public function member() { return $this->belongsTo(Member::class); }
    public function fromUnit() { return $this->belongsTo(OrganizationUnit::class, 'from_unit_id'); }
    public function toUnit() { return $this->belongsTo(OrganizationUnit::class, 'to_unit_id'); }
}

