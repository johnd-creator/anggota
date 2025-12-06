<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiSnapshot extends Model
{
    protected $fillable = ['completeness_pct','mutation_sla_breach_pct','card_downloads','calculated_at'];
}

