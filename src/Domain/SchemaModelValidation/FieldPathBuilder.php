<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation;

use Polymorph\Platform\Domain\Records\Support\RecordPayloadPathRegistry;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\Cardinality;

/**
 * Построитель путей полей для валидации и доступа к data_json.
 *
 * Преобразует full_path из Path в путь с учётом cardinality родительских полей.
 * Используется для:
 * - Валидации (с префиксом {@see RecordPayloadPathRegistry::dottedPrefix()})
 * - Извлечения данных через data_get() (без префикса)
 * - Построения путей для GraphBuilder
 */
final class FieldPathBuilder
{
    private const ARRAY_ELEMENT_WILDCARD = '.*';

    /**
     * Построить путь поля в точечной нотации для валидации.
     *
     * Преобразует full_path из Path в путь для data_json.
     * Если родительский путь имеет cardinality: 'many', заменяет соответствующий сегмент на '*'.
     *
     * @param  string  $fullPath  Полный путь из Path (например, 'author.contacts.phone')
     * @param  array<string, string>  $pathCardinalities  Маппинг full_path → cardinality для всех путей
     * @param  string  $prefix  Префикс для пути (по умолчанию {@see RecordPayloadPathRegistry::dottedPrefix()})
     * @return string Путь в точечной нотации для валидации
     */
    public function buildFieldPath(
        string $fullPath,
        array $pathCardinalities,
        ?string $prefix = null,
    ): string {
        $prefix ??= RecordPayloadPathRegistry::dottedPrefix();
        $segments = explode('.', $fullPath);
        $resultSegments = [];
        $wildcardSegment = ltrim(self::ARRAY_ELEMENT_WILDCARD, '.');

        // Обрабатываем каждый сегмент пути
        for ($i = 0; $i < count($segments); $i++) {
            // Строим путь до текущего сегмента для проверки cardinality
            $parentPath = implode('.', array_slice($segments, 0, $i));

            // Если это не первый сегмент, проверяем cardinality родительского пути
            if ($i > 0 && isset($pathCardinalities[$parentPath]) && $pathCardinalities[$parentPath] === Cardinality::MANY->value) {
                // Родительский путь - массив, заменяем текущий сегмент на '*'
                $resultSegments[] = $wildcardSegment;
                // Добавляем имя сегмента после '*'
                $resultSegments[] = $segments[$i];
            } else {
                // Обычный сегмент пути
                $resultSegments[] = $segments[$i];
            }
        }

        return $prefix.implode('.', $resultSegments);
    }

    /**
     * Построить путь для доступа к данным через data_get().
     *
     * Аналогичен buildFieldPath(), но без префикса.
     * Используется для извлечения значений из Record.data_json с учётом вложенных массивов.
     *
     * Примеры:
     * - 'author' в†’ 'author'
     * - 'meta.author' (meta cardinality=one) в†’ 'meta.author'
     * - 'items.product' (items cardinality=many) в†’ 'items.*.product'
     *
     * @param  string  $fullPath  Полный путь из Field (например, 'items.product')
     * @param  array<string, string>  $pathCardinalities  Маппинг full_path → cardinality для всех путей
     * @return string Путь для data_get() с wildcard'ами
     */
    public function buildDataJsonPath(string $fullPath, array $pathCardinalities): string
    {
        return $this->buildFieldPath($fullPath, $pathCardinalities, '');
    }

    /**
     * Build the effective runtime path used with data_get().
     *
     * The trailing wildcard is required for scalar many fields but must be omitted
     * for JSON containers because their children already contribute wildcard segments.
     *
     * @param  array<string, string>  $pathCardinalities
     */
    public function computeDataPath(
        string $fullPath,
        string $cardinality,
        array $pathCardinalities,
        bool $isJsonType,
    ): string {
        $dataPath = $this->buildDataJsonPath($fullPath, $pathCardinalities);

        if ($cardinality === Cardinality::MANY->value && ! $isJsonType && ! str_ends_with($dataPath, self::ARRAY_ELEMENT_WILDCARD)) {
            $dataPath .= self::ARRAY_ELEMENT_WILDCARD;
        }

        return $dataPath;
    }
}
