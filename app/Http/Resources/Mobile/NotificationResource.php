<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = is_array($this->data) ? $this->data : (array) $this->data;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'message' => $this->message ?? ($data['message'] ?? $data['title'] ?? ''),
            'category' => $data['category'] ?? $this->type ?? 'general',
            'link' => $data['link'] ?? $data['cta_url'] ?? null,
            'data' => $data,
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
