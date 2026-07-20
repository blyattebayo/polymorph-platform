<?php

namespace Database\Factories;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\FieldRefConstraint;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Polymorph\Platform\Domain\SchemaModel\Core\Models\FieldRefConstraint>
 */
class FieldRefConstraintFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = FieldRefConstraint::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'field_id' => Field::factory(),
            'allowed_record_definition_id' => RecordDefinition::factory(),
        ];
    }

    /**
     * Set the allowed record definition.
     */
    public function forRecordDefinition(int $recordDefinitionId): static
    {
        return $this->state(fn (array $attributes) => [
            'allowed_record_definition_id' => $recordDefinitionId,
        ]);
    }

    /**
     * Set the field.
     */
    public function forField(int $fieldId): static
    {
        return $this->state(fn (array $attributes) => [
            'field_id' => $fieldId,
        ]);
    }
}
