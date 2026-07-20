<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;

/**
 * @extends Factory<RecordDefinition>
 */
class RecordDefinitionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = RecordDefinition::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
        ];
    }
}
