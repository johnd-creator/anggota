<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DuesPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'period' => $this['period'],
            'status' => $this['status'],
            'amount' => (float) $this['amount'],
            'paid_at' => $this['paid_at'],
            'notes' => $this['notes'],
        ];
    }
}
