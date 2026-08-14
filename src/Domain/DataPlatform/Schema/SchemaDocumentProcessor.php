<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Schema;

use Illuminate\Support\Str;
use Polymorph\Platform\Domain\DataPlatform\Fields\Cardinality;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldTypeRegistry;
use Polymorph\Platform\Domain\DataPlatform\Fields\OccurrencePath;
use Polymorph\Platform\Domain\DataPlatform\Validation\DataValidationException;

/** Applies structural validation, stable identities and value handlers to a compiled tree. */
final class SchemaDocumentProcessor
{
    public function __construct(private readonly FieldTypeRegistry $types) {}

    /**
     * Validates declared object boundaries and assigns IDs only to json/many items.
     *
     * @param  array<string,mixed>  $document
     * @param  array<string,mixed>  $before
     * @return array<string,mixed>
     */
    public function prepare(CompiledSchemaTree $tree, array $document, array $before = []): array
    {
        $seenIds = [];

        return $this->prepareObject($tree, null, $document, $before, '$', false, $seenIds);
    }

    /** @param array<string,mixed> $document @return array<string,mixed> */
    public function normalizeAndValidate(CompiledSchemaTree $tree, array $document): array
    {
        return $this->normalizeObject($tree, null, $document, '$');
    }

    /**
     * JSON Merge Patch semantics used by record PATCH: maps merge and lists replace.
     * Explicit null remains a value in the platform write contract.
     *
     * @param  array<string,mixed>  $stored
     * @param  array<string,mixed>  $patch
     * @return array<string,mixed>
     */
    public function mergePatch(array $stored, array $patch): array
    {
        foreach ($patch as $key => $value) {
            $current = $stored[$key] ?? null;
            $stored[$key] = $this->isObject($value) && $this->isObject($current)
                ? $this->mergePatch($current, $value)
                : $value;
        }

        return $stored;
    }

    /**
     * @param  array<string,mixed>  $object
     * @param  array<string,mixed>  $before
     * @param  array<string,true>  $seenIds
     * @return array<string,mixed>
     */
    private function prepareObject(
        CompiledSchemaTree $tree,
        ?FieldDefinition $container,
        array $object,
        array $before,
        string $occurrence,
        bool $allowItemId,
        array &$seenIds,
    ): array {
        if ($object !== [] && array_is_list($object)) {
            throw DataValidationException::one('container_type', 'Expected an object.', $container?->path ?? '$', $occurrence);
        }
        $children = $container === null ? $tree->roots() : $tree->childrenOf($container);
        $known = array_fill_keys(array_map(static fn (FieldDefinition $field): string => $field->name, $children), true);
        foreach ($object as $key => $_) {
            if ($key === '_item_id' && $allowItemId) {
                continue;
            }
            if (! isset($known[$key])) {
                $unknownPath = $container === null ? (string) $key : $container->path.'.'.$key;
                throw DataValidationException::one(
                    'unknown_fields',
                    "Unknown field '{$unknownPath}'.",
                    $unknownPath,
                    $occurrence,
                    ['fields' => [$key]],
                );
            }
        }

        foreach ($children as $field) {
            if (! array_key_exists($field->name, $object) || $object[$field->name] === null || $field->typeName() !== 'json') {
                continue;
            }
            $value = $object[$field->name];
            $previous = $before[$field->name] ?? null;
            $fieldOccurrence = $occurrence.'.'.$field->name;
            if ($field->cardinality === Cardinality::ONE) {
                if (! $this->isObject($value)) {
                    throw DataValidationException::one('container_type', 'Expected an object.', $field->path, $fieldOccurrence);
                }
                $object[$field->name] = $this->prepareObject(
                    $tree,
                    $field,
                    $value,
                    $this->isObject($previous) ? $previous : [],
                    $fieldOccurrence,
                    false,
                    $seenIds,
                );

                continue;
            }
            if (! is_array($value) || ! array_is_list($value)) {
                throw DataValidationException::one('container_type', 'Expected a list of objects.', $field->path, $fieldOccurrence);
            }
            $previousItems = is_array($previous) && array_is_list($previous) ? $previous : [];
            $allowedIds = [];
            foreach ($previousItems as $item) {
                if ($this->isObject($item) && is_string($item['_item_id'] ?? null)) {
                    $allowedIds[$item['_item_id']] = true;
                }
            }
            foreach ($value as $index => $item) {
                $itemOccurrence = OccurrencePath::appendPosition($fieldOccurrence, $index);
                if (! $this->isObject($item)) {
                    throw DataValidationException::one('container_type', 'Expected an object item.', $field->path, $itemOccurrence);
                }
                $provided = $item['_item_id'] ?? null;
                if ($provided !== null && (! is_string($provided) || ! Str::isUlid($provided))) {
                    throw DataValidationException::one('invalid_item_id', 'Repeated object item ID must be a ULID.', $field->path, $itemOccurrence);
                }
                if (is_string($provided) && ! isset($allowedIds[$provided])) {
                    throw DataValidationException::one('foreign_item_id', 'Repeated object item ID does not belong to this occurrence.', $field->path, $itemOccurrence);
                }
                $itemId = is_string($provided) ? $provided : (string) Str::ulid();
                if (isset($seenIds[$itemId])) {
                    throw DataValidationException::one('duplicate_item_id', 'Repeated object item IDs must be unique.', $field->path, $itemOccurrence);
                }
                $seenIds[$itemId] = true;
                $item['_item_id'] = $itemId;
                $previousItem = [];
                foreach ($previousItems as $candidate) {
                    if ($this->isObject($candidate) && ($candidate['_item_id'] ?? null) === $itemId) {
                        $previousItem = $candidate;
                        break;
                    }
                }
                $value[$index] = $this->prepareObject(
                    $tree,
                    $field,
                    $item,
                    $previousItem,
                    OccurrencePath::appendDocumentItem($fieldOccurrence, $item, $index),
                    true,
                    $seenIds,
                );
            }
            $object[$field->name] = $value;
        }

        return $object;
    }

    /** @param array<string,mixed> $object @return array<string,mixed> */
    private function normalizeObject(
        CompiledSchemaTree $tree,
        ?FieldDefinition $container,
        array $object,
        string $occurrence,
    ): array {
        $children = $container === null ? $tree->roots() : $tree->childrenOf($container);
        foreach ($children as $field) {
            $handler = $this->types->get($field->type);
            $fieldOccurrence = $occurrence.'.'.$field->name;
            if (! array_key_exists($field->name, $object)) {
                $handler->validateValue(null, $field, $fieldOccurrence);

                continue;
            }
            $normalized = $handler->normalize($object[$field->name], $field, $fieldOccurrence);
            $handler->validateValue($normalized, $field, $fieldOccurrence);
            if ($normalized !== null && $field->typeName() === 'json') {
                if ($field->cardinality === Cardinality::ONE) {
                    $normalized = $this->normalizeObject($tree, $field, $normalized, $fieldOccurrence);
                } else {
                    foreach ($normalized as $index => $item) {
                        $normalized[$index] = $this->normalizeObject(
                            $tree,
                            $field,
                            $item,
                            OccurrencePath::appendPosition($fieldOccurrence, $index),
                        );
                    }
                }
            }
            $object[$field->name] = $normalized;
        }

        return $object;
    }

    private function isObject(mixed $value): bool
    {
        // DatabaseJson decodes JSON objects to associative arrays; the empty
        // object and empty list both become []. The compiled field cardinality
        // supplies the otherwise-lost distinction at this boundary.
        return is_array($value) && ($value === [] || ! array_is_list($value));
    }
}
