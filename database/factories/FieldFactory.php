<?php

namespace Database\Factories;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\Cardinality;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Polymorph\Platform\Domain\SchemaModel\Core\Models\Field>
 */
class FieldFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Field::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'schema_id' => SchemaModel::factory(),
            'parent_id' => null,
            'name' => $name,
            'full_path' => $name,
            'type' => fake()->randomElement([
                FieldType::STRING,
                FieldType::TEXT,
                FieldType::INT,
                FieldType::BOOL,
            ]),
            'cardinality' => Cardinality::ONE,
            'is_indexed' => false,
            'validation_rules' => null,
            'sort_order' => 0,
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the field is required.
     */
    public function required(): static
    {
        return $this->state(function (array $attributes): array {
            $rules = (array) ($attributes['validation_rules'] ?? []);
            $rules['required'] = true;

            return [
                'validation_rules' => $rules,
            ];
        });
    }

    /**
     * Indicate that the field is indexed.
     */
    public function indexed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_indexed' => true,
        ]);
    }

    /**
     * Indicate that the field has many cardinality.
     */
    public function many(): static
    {
        return $this->state(fn (array $attributes) => [
            'cardinality' => Cardinality::MANY,
        ]);
    }

    /**
     * Set the field type.
     */
    public function type(FieldType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }

    /**
     * Set the field as a child of a parent field.
     */
    public function child(Field $parent): static
    {
        return $this->state(function (array $attributes) use ($parent): array {
            $name = (string) $attributes['name'];
            $fullPath = $parent->full_path . '.' . $name;

            return [
                'parent_id' => $parent->id,
                'schema_id' => $parent->schema_id,
                'full_path' => $fullPath,
            ];
        });
    }

    /**
     * Set specific name and full path.
     */
    public function withPath(string $name, ?string $fullPath = null): static
    {
        $resolvedPath = $fullPath ?? $name;

        return $this->state(fn (array $attributes) => [
            'name' => $name,
            'full_path' => $resolvedPath,
        ]);
    }
}
