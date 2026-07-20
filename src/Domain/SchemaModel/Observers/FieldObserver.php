<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Observers;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\SchemaModel\Events\FieldAdded;
use Polymorph\Platform\Domain\SchemaModel\Events\FieldDeleted;
use Polymorph\Platform\Domain\SchemaModel\Events\FieldPathChanged;
use Polymorph\Platform\Domain\SchemaModel\Events\FieldUpdated;

/**
 * Observer для модели Field.
 *
 * Только диспатчит доменные события. Побочные эффекты (инвалидация кэша снапшота,
 * кэша правил валидации, перестроение display-view) выполняют листенеры на
 * SchemaChangeEvent в соответствующих доменах. Диспатч синхронный, поэтому
 * эффекты применяются в той же точке выполнения, что и прежде.
 */
class FieldObserver
{
    public function created(Field $field): void
    {
        event(new FieldAdded($field));
    }

    public function updated(Field $field): void
    {
        $changes = $field->getChanges();

        event(new FieldUpdated($field, $changes));

        // Отдельное событие, если изменился путь.
        if (isset($changes['full_path'])) {
            event(new FieldPathChanged(
                $field,
                $field->getOriginal('full_path'),
                $changes['full_path']
            ));
        }
    }

    public function deleted(Field $field): void
    {
        event(new FieldDeleted($field->id, $field->full_path, $field->schema_id));
    }
}
