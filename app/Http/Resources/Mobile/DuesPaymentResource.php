<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DuesPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'] ?? null,
            'member_id' => $this['member_id'] ?? null,
            'member_name' => $this->whenLoaded('member', fn () => $this->member?->full_name),
            'kta_number' => $this->whenLoaded('member', fn () => $this->member?->kta_number),
            'organization_unit_id' => $this['organization_unit_id'] ?? null,
            'period' => $this['period'],
            'status' => $this['status'],
            'amount' => (float) $this['amount'],
            'paid_at' => $this['paid_at'],
            'notes' => $this['notes'],
        ];
    }
}
