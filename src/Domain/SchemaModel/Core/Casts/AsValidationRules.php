<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Casts;

use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\ValidationRules;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent Cast для автоматической конвертации ValidationRules в/из JSON.
 */
class AsValidationRules implements CastsAttributes
{
    /**
     * Преобразовать значение из БД в Value Object.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?ValidationRules
    {
        if ($value === null) {
            return null;
        }

        $decoded = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($decoded)) {
            return null;
        }

        return ValidationRules::fromArray($decoded);
    }

    /**
     * Подготовить Value Object для сохранения в БД.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ValidationRules) {
            return json_encode($value->toArray());
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return null;
    }
}
