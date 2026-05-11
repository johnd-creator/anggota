<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DuesPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $memberName = null;
        $ktaNumber = null;

        if (! is_array($this->resource) && $this->resource->relationLoaded('member')) {
            $memberName = $this->resource->member?->full_name;
            $ktaNumber = $this->resource->member?->kta_number;
        }

        return [
            'id' => $this['id'] ?? null,
            'member_id' => $this['member_id'] ?? null,
            'member_name' => $memberName,
            'kta_number' => $ktaNumber,
            'organization_unit_id' => $this['organization_unit_id'] ?? null,
            'period' => $this['period'],
            'status' => $this['status'],
            'amount' => (float) $this['amount'],
            'paid_at' => $this['paid_at'],
            'notes' => $this['notes'],
        ];
    }
}
