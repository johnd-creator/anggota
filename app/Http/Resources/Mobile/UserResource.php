<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'role' => $this->whenLoaded('role', fn () => [
                'id' => $this->role?->id,
                'name' => $this->role?->name,
                'label' => $this->role?->label ?? $this->role?->name,
            ]),
            'current_unit_id' => $this->currentUnitId(),
            'member_context_unit_id' => $this->memberContextUnitId(),
            'member' => $this->whenLoaded('linkedMember', fn () => $this->linkedMember ? new MemberResource($this->linkedMember) : null),
        ];
    }
}
