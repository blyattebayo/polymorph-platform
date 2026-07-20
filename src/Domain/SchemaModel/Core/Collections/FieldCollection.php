<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Collections;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Illuminate\Database\Eloquent\Collection;

/**
 * Типизированная коллекция для Field моделей.
 * 
 * Добавляет методы для работы с полями: фильтрация, поиск, построение дерева.
 *
 * @extends Collection<int, Field>
 */
class FieldCollection extends Collection
{
}
