<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\Records\Core\Models\Record;
use Polymorph\Platform\Domain\Users\Core\Models\User;

/**
 * @extends Factory<Record>
 */
class RecordFactory extends Factory
{
    protected $model = Record::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'record_definition_id' => RecordDefinition::factory(),
            'author_id' => UserFactory::new(),
            'data_json' => [],
        ];
    }

    /**
     * Indicate that the record is for a specific record definition.
     */
    public function forRecordDefinition(RecordDefinition $recordDefinition): static
    {
        return $this->state(fn (array $attributes) => [
            'record_definition_id' => $recordDefinition->id,
        ]);
    }

    /**
     * Indicate that the record has a specific author.
     */
    public function byAuthor(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'author_id' => $user->id,
        ]);
    }
}
