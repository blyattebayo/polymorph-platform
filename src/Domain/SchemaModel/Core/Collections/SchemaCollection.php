<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Collections;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Illuminate\Database\Eloquent\Collection;

/**
 * Типизированная коллекция для SchemaModel.
 * 
 * Добавляет методы для работы со схемами: поиск, фильтрация.
 *
 * @extends Collection<int, SchemaModel>
 */
class SchemaCollection extends Collection
{
}
