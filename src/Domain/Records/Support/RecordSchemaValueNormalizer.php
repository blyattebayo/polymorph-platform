<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Support;

use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\Cardinality;
use Polymorph\Platform\Domain\SchemaModelValidation\Schema\SchemaDescriptorProvider;
use Polymorph\Platform\Domain\SchemaModelValidation\Schema\FieldDescriptor;

/**
 * Нормализует значения schema-полей перед записью в data_json (path-aware).
 */
final class RecordSchemaValueNormalizer
{
    public function __construct(
        private readonly SchemaDescriptorProvider $schemaDescriptorProvider,
        private readonly RecordSchemaScalarValueNormalizer $scalarNormalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizeForSchema(int $schemaId, array $payload): array
    {
        $descriptor = $this->schemaDescriptorProvider->forSchemaId($schemaId);

        foreach ($descriptor->fields as $field) {
            if ($field->isSystem || $field->type->isContainer()) {
                continue;
            }

            $payload = $this->normalizeField($field, $payload);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeField(FieldDescriptor $field, array $payload): array
    {
        $normalizer = fn (mixed $value): mixed => $this->scalarNormalizer->normalize($field->type, $value);

        if ($field->cardinality === Cardinality::MANY->value) {
            return $this->normalizeAtPath($payload, $field->dataPath, $normalizer);
        }

        return $this->normalizeAtPath($payload, $this->scalarPath($field->dataPath), $normalizer);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeAtPath(array $payload, string $dataPath, callable $normalize): array
    {
        if (str_contains($dataPath, '.*.')) {
            return $this->normalizeNestedWildcardPath($payload, $dataPath, $normalize);
        }

        if (str_ends_with($dataPath, '.*')) {
            return $this->normalizeManyScalarPath($payload, $dataPath, $normalize);
        }

        $value = data_get($payload, $dataPath);
        if ($value === null) {
            return $payload;
        }

        data_set($payload, $dataPath, $normalize($value));

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeManyScalarPath(array $payload, string $dataPath, callable $normalize): array
    {
        $containerPath = substr($dataPath, 0, -2);
        if (! array_key_exists($containerPath, $payload)) {
            return $payload;
        }

        $values = $payload[$containerPath];
        if (! is_array($values)) {
            return $payload;
        }

        $payload[$containerPath] = array_map(
            fn (mixed $value): mixed => $value === null ? null : $normalize($value),
            array_values($values),
        );

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeNestedWildcardPath(array $payload, string $dataPath, callable $normalize): array
    {
        [$containerPath, $leafPath] = explode('.*.', $dataPath, 2);
        if (! array_key_exists($containerPath, $payload) || ! is_array($payload[$containerPath])) {
            return $payload;
        }

        foreach ($payload[$containerPath] as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $value = data_get($item, $leafPath);
            if ($value === null) {
                continue;
            }

            data_set($payload[$containerPath][$index], $leafPath, $normalize($value));
        }

        return $payload;
    }

    private function scalarPath(string $dataPath): string
    {
        return rtrim($dataPath, '.*');
    }
}
