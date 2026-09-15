<?php

namespace App\Http\Resources;

use App\Models\Environment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Environment
 */
class EnvironmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'tone' => $this->type->tone(),
            'assets_count' => $this->whenCounted('assets'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
