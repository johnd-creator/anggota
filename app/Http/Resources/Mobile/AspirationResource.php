<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AspirationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canViewCreator = (bool) ($this->can_view_creator ?? false);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status,
            'support_count' => (int) $this->support_count,
            'is_anonymous' => (bool) $this->is_anonymous,
            'is_supported' => (bool) ($this->is_supported ?? false),
            'is_own' => (bool) ($this->is_own ?? false),
            'organization_unit_id' => $this->organization_unit_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
            ]),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')->values()),
            'creator' => $canViewCreator || ! $this->is_anonymous ? [
                'member_id' => $this->member_id,
                'member_name' => $this->member?->full_name,
                'user_id' => $this->user_id,
                'user_name' => $this->user?->name,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
