<?php

namespace Database\Factories;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition>
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

