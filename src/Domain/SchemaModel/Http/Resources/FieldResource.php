<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldType;

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
            'constraints' => $this->constraints(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'children' => $this->whenLoaded('children', fn () => FieldResource::collection($this->children)),
        ];
    }

    /** @return array{constraint_id?:int,allowed_record_definition_id?:int,allowed_mimes?:string[]}|null */
    private function constraints(): ?array
    {
        if ($this->type === FieldType::REF) {
            return $this->refConstraint === null ? null : [
                'constraint_id' => (int) $this->refConstraint->id,
                'allowed_record_definition_id' => (int) $this->refConstraint->allowed_record_definition_id,
            ];
        }

        if ($this->type === FieldType::MEDIA) {
            return ['allowed_mimes' => $this->mediaConstraints->pluck('allowed_mime')->all()];
        }

        return null;
    }
}
