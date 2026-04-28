<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'date' => $this->date?->toDateString(),
            'description' => $this->description,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'rejected_reason' => $this->rejected_reason,
            'organization_unit' => $this->whenLoaded('organizationUnit', fn () => [
                'id' => $this->organizationUnit?->id,
                'name' => $this->organizationUnit?->name,
                'code' => $this->organizationUnit?->code,
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'type' => $this->category?->type,
            ]),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
            ]),
            'permissions' => [
                'view' => $request->user()?->can('view', $this->resource) ?? false,
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
                'approve' => $request->user()?->can('approve', $this->resource) ?? false,
                'reject' => $request->user()?->can('reject', $this->resource) ?? false,
            ],
        ];
    }
}
