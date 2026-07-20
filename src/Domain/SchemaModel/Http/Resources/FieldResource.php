<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Http\Resources;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Field
 */
class FieldResource extends JsonResource
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
            'schema_id' => $this->schema_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'full_path' => $this->full_path,
            'type' => $this->type->value,
            'cardinality' => $this->cardinality->value,
            'is_indexed' => $this->is_indexed,
            'is_system' => $this->is_system,
            'validation_rules' => $this->validation_rules?->toArray() ?: null,
            'sort_order' => $this->sort_order,
            'metadata' => $this->metadata,
            'constraints' => $this->getAllConstraints(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
