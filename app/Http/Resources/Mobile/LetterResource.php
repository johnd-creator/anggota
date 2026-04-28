<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LetterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'letter_number' => $this->letter_number,
            'subject' => $this->subject,
            'status' => $this->status,
            'to_type' => $this->to_type,
            'signer_type' => $this->signer_type,
            'signer_type_secondary' => $this->signer_type_secondary,
            'confidentiality' => $this->confidentiality,
            'urgency' => $this->urgency,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'rejected_at' => $this->rejected_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'sla_due_at' => $this->sla_due_at?->toISOString(),
            'sla_status' => $this->sla_status,
            'is_overdue' => (bool) $this->is_overdue,
            'age_hours' => $this->age_hours,
            'from_unit' => $this->whenLoaded('fromUnit', fn () => [
                'id' => $this->fromUnit?->id,
                'name' => $this->fromUnit?->name,
                'code' => $this->fromUnit?->code,
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'code' => $this->category?->code,
            ]),
            'to_unit' => $this->whenLoaded('toUnit', fn () => [
                'id' => $this->toUnit?->id,
                'name' => $this->toUnit?->name,
                'code' => $this->toUnit?->code,
            ]),
            'to_member' => $this->whenLoaded('toMember', fn () => [
                'id' => $this->toMember?->id,
                'full_name' => $this->toMember?->full_name,
                'kta_number' => $this->toMember?->kta_number,
            ]),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
            ]),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'mime' => $attachment->mime,
                'size' => $attachment->size,
            ])->values()),
            'permissions' => [
                'view' => $request->user()?->can('view', $this->resource) ?? false,
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
                'submit' => $request->user()?->can('submit', $this->resource) ?? false,
                'approve' => $request->user()?->can('approve', $this->resource) ?? false,
                'revise' => $request->user()?->can('revise', $this->resource) ?? false,
                'reject' => $request->user()?->can('reject', $this->resource) ?? false,
                'send' => $request->user()?->can('send', $this->resource) ?? false,
                'archive' => $request->user()?->can('archive', $this->resource) ?? false,
            ],
        ];
    }
}
