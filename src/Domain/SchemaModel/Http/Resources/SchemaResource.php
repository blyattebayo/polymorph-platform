<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;

/**
 * @mixin SchemaModel
 */
class SchemaResource extends JsonResource
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
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'owner' => $this->ownership->toOwner()->toApiArray(),
            'fields' => $this->whenLoaded('fields', fn () => FieldResource::collection($this->fields)),
            'fields_count' => $this->whenCounted('fields'),
            'record_definitions_count' => $this->when(
                isset($this->record_definitions_count),
                (int) $this->record_definitions_count
            ),
            'usage_count' => $this->when(
                isset($this->usage_count),
                $this->usage_count
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
