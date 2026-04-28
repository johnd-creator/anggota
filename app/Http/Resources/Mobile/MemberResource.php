<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'birth_place' => $this->birth_place,
            'birth_date' => $this->birth_date?->toDateString(),
            'gender' => $this->gender,
            'company_join_date' => $this->company_join_date?->toDateString(),
            'address' => $this->address,
            'emergency_contact' => $this->emergency_contact,
            'job_title' => $this->job_title,
            'employment_type' => $this->employment_type,
            'status' => $this->status,
            'join_date' => $this->join_date?->toDateString(),
            'kta_number' => $this->kta_number,
            'nra' => $this->nra,
            'nip' => $this->nip,
            'photo_url' => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null,
            'organization_unit' => $this->whenLoaded('unit', fn () => [
                'id' => $this->unit?->id,
                'name' => $this->unit?->name,
                'code' => $this->unit?->code,
            ]),
            'union_position' => $this->whenLoaded('unionPosition', fn () => [
                'id' => $this->unionPosition?->id,
                'name' => $this->unionPosition?->name,
            ]),
            'documents' => $this->whenLoaded('documents', fn () => $this->documents->map(fn ($document) => [
                'id' => $document->id,
                'type' => $document->type,
                'original_name' => $document->original_name,
                'size' => $document->size,
                'updated_at' => $document->updated_at?->toISOString(),
            ])->values()),
        ];
    }
}
