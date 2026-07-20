<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;

/**
 * @extends Factory<SchemaModel>
 */
class SchemaModelFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SchemaModel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'code' => fake()->unique()->slug(2),
            'description' => fake()->optional()->sentence(),
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the schema has metadata.
     */
    public function withMetadata(array $metadata): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => $metadata,
        ]);
    }
}
