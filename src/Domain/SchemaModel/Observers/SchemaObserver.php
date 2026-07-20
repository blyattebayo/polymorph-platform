<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Observers;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Core\SystemFields\SystemFieldSeeder;
use Polymorph\Platform\Domain\SchemaModel\Events\SchemaCreated;
use Polymorph\Platform\Domain\SchemaModel\Events\SchemaDeleted;
use Polymorph\Platform\Domain\SchemaModel\Events\SchemaUpdated;

/**
 * Observer для модели Schema.
 *
 * Засевает системные поля при создании и диспатчит доменные события. Побочные
 * эффекты (кэш снапшота, кэш правил, перестроение display-view) выполняют
 * листенеры на SchemaChangeEvent в соответствующих доменах.
 */
class SchemaObserver
{
    public function __construct(
        private readonly SystemFieldSeeder $systemFieldSeeder,
    ) {
    }

    public function created(SchemaModel $schema): void
    {
        $this->systemFieldSeeder->seedFor($schema);

        event(new SchemaCreated($schema));
    }

    public function updated(SchemaModel $schema): void
    {
        event(new SchemaUpdated($schema, $schema->getChanges()));
    }

    public function deleted(SchemaModel $schema): void
    {
        event(new SchemaDeleted($schema->id, $schema->code));
    }
}
