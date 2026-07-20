<?php

declare(strict_types=1);

namespace Database\Factories;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\EntryView\Core\Models\EntryView;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Polymorph\Platform\Domain\EntryView\Core\Models\EntryView>
 */
class EntryViewFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = EntryView::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'record_definition_id' => RecordDefinition::factory(),
            'schema_id' => SchemaModel::factory(),
            'config_json' => [],
        ];
    }

    /**
     * Установить конфигурацию формы.
     *
     * @param array<string, mixed> $config Конфигурация (ключ - full_path, значение - EditComponent)
     * @return static
     */
    public function withConfig(array $config): static
    {
        return $this->state(fn (array $attributes) => [
            'config_json' => $config,
        ]);
    }
}
