<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Schema;

use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;

/** Immutable canonical schema representation consumed by every runtime. */
final class CompiledSchemaTree
{
    /** @var array<string,FieldDefinition> */
    private array $byId = [];

    /** @var array<string,FieldDefinition> */
    private array $byPath = [];

    /** @var array<string,list<FieldDefinition>> */
    private array $children = [];

    /** @var list<FieldDefinition> */
    private array $roots = [];

    /** @param list<FieldDefinition> $fields */
    public function __construct(private readonly array $fields)
    {
        foreach ($fields as $field) {
            $this->byId[$field->id] = $field;
            $this->byPath[$field->path] = $field;
            if ($field->parentId === null) {
                $this->roots[] = $field;
            } else {
                $this->children[$field->parentId][] = $field;
            }
        }
        $sort = static fn (FieldDefinition $left, FieldDefinition $right): int => [
            $left->position, $left->name, $left->id,
        ] <=> [
            $right->position, $right->name, $right->id,
        ];
        usort($this->roots, $sort);
        foreach ($this->children as &$children) {
            usort($children, $sort);
        }
    }

    /** @return list<FieldDefinition> */
    public function fields(): array
    {
        return $this->fields;
    }

    /** @return list<FieldDefinition> */
    public function roots(): array
    {
        return $this->roots;
    }

    /** @return list<FieldDefinition> */
    public function childrenOf(FieldDefinition|string $parent): array
    {
        $id = $parent instanceof FieldDefinition ? $parent->id : $parent;

        return $this->children[$id] ?? [];
    }

    public function field(string $idOrPath): FieldDefinition
    {
        $field = $this->byId[$idOrPath] ?? $this->byPath[$idOrPath] ?? null;
        if (! $field instanceof FieldDefinition) {
            throw DataPlatformBadRequest::because(
                'unknown_schema_field',
                "Schema field '{$idOrPath}' does not exist.",
                ['field' => $idOrPath],
            );
        }

        return $field;
    }

    /** @return list<FieldDefinition> Root through direct parent. */
    public function ancestors(FieldDefinition|string $field): array
    {
        $field = $field instanceof FieldDefinition ? $field : $this->field($field);
        $ancestors = [];
        $parentId = $field->parentId;
        while ($parentId !== null) {
            $parent = $this->byId[$parentId];
            array_unshift($ancestors, $parent);
            $parentId = $parent->parentId;
        }

        return $ancestors;
    }

    public function plan(FieldDefinition|string $field): FieldTraversalPlan
    {
        $field = $field instanceof FieldDefinition ? $field : $this->field($field);

        return new FieldTraversalPlan($field, [...$this->ancestors($field), $field]);
    }

    /** @return list<array{occurrence:string,value:mixed}> */
    public function values(array $document, FieldDefinition|string $field): array
    {
        return $this->plan($field)->values($document);
    }

    /** @param callable(mixed,string):mixed $mapper @return array<string,mixed> */
    public function map(array $document, FieldDefinition|string $field, callable $mapper): array
    {
        return $this->plan($field)->map($document, $mapper);
    }

    /** Builds a PATCH fragment for a field whose ancestor chain contains no repeated container. */
    public function patch(FieldDefinition|string $field, mixed $value): array
    {
        $field = $field instanceof FieldDefinition ? $field : $this->field($field);
        $patch = [$field->name => $value];
        foreach (array_reverse($this->ancestors($field)) as $ancestor) {
            if ($ancestor->cardinality->value === 'many') {
                throw DataPlatformBadRequest::because(
                    'ambiguous_repeated_field_patch',
                    "Field '{$field->path}' requires an occurrence-scoped update.",
                    ['field_id' => $field->id, 'path' => $field->path],
                );
            }
            $patch = [$ancestor->name => $patch];
        }

        return $patch;
    }

    /** @param callable(array<string,mixed>):array<string,mixed> $mapper @return array<string,mixed> */
    public function mapObjectOccurrences(
        array $document,
        FieldDefinition|string|null $container,
        callable $mapper,
    ): array {
        if ($container === null) {
            return $mapper($document);
        }
        $container = $container instanceof FieldDefinition ? $container : $this->field($container);

        return $this->map($document, $container, static function (mixed $value) use ($container, $mapper): mixed {
            if ($value === null) {
                return null;
            }
            if ($container->cardinality->value === 'one') {
                return is_array($value) && ($value === [] || ! array_is_list($value)) ? $mapper($value) : $value;
            }
            if (! is_array($value) || ! array_is_list($value)) {
                return $value;
            }
            foreach ($value as $index => $item) {
                if (is_array($item) && ($item === [] || ! array_is_list($item))) {
                    $value[$index] = $mapper($item);
                }
            }

            return $value;
        });
    }
}
