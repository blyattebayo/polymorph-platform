<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Collections;

use Illuminate\Database\Eloquent\Collection;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;

/**
 * Типизированная коллекция для SchemaModel.
 *
 * Добавляет методы для работы со схемами: поиск, фильтрация.
 *
 * @extends Collection<int, SchemaModel>
 */
class SchemaCollection extends Collection {}
