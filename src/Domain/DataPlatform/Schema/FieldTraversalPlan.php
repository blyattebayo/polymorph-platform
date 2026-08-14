<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Schema;

use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Fields\OccurrencePath;

/** Schema-compiled traversal for one field, including exact collection boundaries. */
final readonly class FieldTraversalPlan
{
    /**
     * @param  list<FieldDefinition>  $lineage  Root through target.
     */
    public function __construct(
        public FieldDefinition $field,
        public array $lineage,
    ) {}

    /** @return list<array{occurrence:string,value:mixed}> */
    public function values(array $document): array
    {
        $values = [];
        $this->collect($document, 0, '$', $values);

        return $values;
    }

    /** @param callable(mixed,string):mixed $mapper @return array<string,mixed> */
    public function map(array $document, callable $mapper): array
    {
        return $this->mapNode($document, 0, '$', $mapper);
    }

    /**
     * @param  list<array{occurrence:string,value:mixed}>  $values
     */
    private function collect(mixed $node, int $depth, string $occurrence, array &$values): void
    {
        if ($depth === count($this->lineage)) {
            $values[] = ['occurrence' => $occurrence, 'value' => $node];

            return;
        }
        if (! is_array($node) || array_is_list($node)) {
            return;
        }

        $segment = $this->lineage[$depth];
        if (! array_key_exists($segment->name, $node)) {
            return;
        }
        $value = $node[$segment->name];
        $fieldOccurrence = $occurrence.'.'.$segment->name;
        $isAncestorCollection = $depth < count($this->lineage) - 1
            && $segment->typeName() === 'json'
            && $segment->cardinality->value === 'many';
        if (! $isAncestorCollection) {
            $this->collect($value, $depth + 1, $fieldOccurrence, $values);

            return;
        }
        if (! is_array($value) || ! array_is_list($value)) {
            return;
        }
        foreach ($value as $index => $item) {
            $this->collect(
                $item,
                $depth + 1,
                OccurrencePath::appendDocumentItem($fieldOccurrence, $item, $index),
                $values,
            );
        }
    }

    /** @param callable(mixed,string):mixed $mapper */
    private function mapNode(mixed $node, int $depth, string $occurrence, callable $mapper): mixed
    {
        if ($depth === count($this->lineage)) {
            return $mapper($node, $occurrence);
        }
        if (! is_array($node) || array_is_list($node)) {
            return $node;
        }

        $segment = $this->lineage[$depth];
        if (! array_key_exists($segment->name, $node)) {
            return $node;
        }
        $fieldOccurrence = $occurrence.'.'.$segment->name;
        $isAncestorCollection = $depth < count($this->lineage) - 1
            && $segment->typeName() === 'json'
            && $segment->cardinality->value === 'many';
        if (! $isAncestorCollection) {
            $node[$segment->name] = $this->mapNode(
                $node[$segment->name],
                $depth + 1,
                $fieldOccurrence,
                $mapper,
            );

            return $node;
        }

        $items = $node[$segment->name];
        if (! is_array($items) || ! array_is_list($items)) {
            return $node;
        }
        foreach ($items as $index => $item) {
            $items[$index] = $this->mapNode(
                $item,
                $depth + 1,
                OccurrencePath::appendDocumentItem($fieldOccurrence, $item, $index),
                $mapper,
            );
        }
        $node[$segment->name] = $items;

        return $node;
    }
}
