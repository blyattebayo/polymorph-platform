<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields;

use Illuminate\Support\Str;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;

/** Traverses logical dotted paths while retaining stable occurrences in nested lists. */
final class DocumentPathAccessor
{
    /** @return list<array{occurrence:string,value:mixed}> */
    public function values(array $document, string $path): array
    {
        $values = [];
        $this->collectValues($document, $this->segments($path), '$', $values);

        return $values;
    }

    /** @param callable(mixed,string):mixed $mapper @return array<string,mixed> */
    public function map(array $document, string $path, callable $mapper): array
    {
        return $this->mapValues($document, $this->segments($path), '$', $mapper);
    }

    /** @return array{bool,mixed} */
    public function get(array $document, string $path): array
    {
        $cursor = $document;
        foreach ($this->segments($path) as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return [false, null];
            }
            $cursor = $cursor[$segment];
        }

        return [true, $cursor];
    }

    /** @return array<string,mixed> */
    public function set(array $document, string $path, mixed $value): array
    {
        $segments = $this->segments($path);
        $last = array_pop($segments);
        $cursor = &$document;
        foreach ($segments as $segment) {
            if (! isset($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            if (! is_array($cursor[$segment]) || ($cursor[$segment] !== [] && array_is_list($cursor[$segment]))) {
                throw DataPlatformBadRequest::because(
                    'document_path_crosses_list',
                    "Document path '{$path}' crosses a list.",
                    ['path' => $path],
                );
            }
            $cursor = &$cursor[$segment];
        }
        $cursor[$last] = $value;

        return $document;
    }

    /** @return array<string,mixed> */
    public function remove(array $document, string $path): array
    {
        $segments = $this->segments($path);
        $last = array_pop($segments);
        $cursor = &$document;
        foreach ($segments as $segment) {
            if (! isset($cursor[$segment]) || ! is_array($cursor[$segment]) || array_is_list($cursor[$segment])) {
                return $document;
            }
            $cursor = &$cursor[$segment];
        }
        unset($cursor[$last]);

        return $document;
    }

    /** @return array<string,mixed> */
    public function ensureStableItemIds(array $document): array
    {
        foreach ($document as $key => $value) {
            if (! is_array($value)) {
                continue;
            }
            if (array_is_list($value)) {
                foreach ($value as $index => $item) {
                    if (is_array($item) && ! array_is_list($item)) {
                        $item['_item_id'] = is_string($item['_item_id'] ?? null) && $item['_item_id'] !== ''
                            ? $item['_item_id']
                            : (string) Str::ulid();
                        $value[$index] = $this->ensureStableItemIds($item);
                    }
                }
                $document[$key] = $value;
            } else {
                $document[$key] = $this->ensureStableItemIds($value);
            }
        }

        return $document;
    }

    /**
     * @param  list<string>  $segments
     * @param  list<array{occurrence:string,value:mixed}>  $values
     */
    private function collectValues(mixed $node, array $segments, string $occurrence, array &$values): void
    {
        if ($segments === []) {
            $values[] = ['occurrence' => $occurrence, 'value' => $node];

            return;
        }
        if (is_array($node) && array_is_list($node)) {
            foreach ($node as $index => $item) {
                $this->collectValues($item, $segments, $this->itemOccurrence($item, $index, $occurrence), $values);
            }

            return;
        }
        if (! is_array($node)) {
            return;
        }

        $segment = $segments[0];
        if (! array_key_exists($segment, $node)) {
            return;
        }

        $this->collectValues(
            $node[$segment],
            array_slice($segments, 1),
            $occurrence.'.'.$segment,
            $values,
        );
    }

    /**
     * @param  list<string>  $segments
     * @param  callable(mixed,string):mixed  $mapper
     */
    private function mapValues(mixed $node, array $segments, string $occurrence, callable $mapper): mixed
    {
        if ($segments === []) {
            return $mapper($node, $occurrence);
        }
        if (is_array($node) && array_is_list($node)) {
            foreach ($node as $index => $item) {
                $node[$index] = $this->mapValues(
                    $item,
                    $segments,
                    $this->itemOccurrence($item, $index, $occurrence),
                    $mapper,
                );
            }

            return $node;
        }
        if (! is_array($node)) {
            return $node;
        }

        $segment = $segments[0];
        if (! array_key_exists($segment, $node)) {
            return $node;
        }

        $node[$segment] = $this->mapValues(
            $node[$segment],
            array_slice($segments, 1),
            $occurrence.'.'.$segment,
            $mapper,
        );

        return $node;
    }

    private function itemOccurrence(mixed $item, int|string $index, string $occurrence): string
    {
        $itemId = is_array($item) && is_string($item['_item_id'] ?? null) ? $item['_item_id'] : (string) $index;

        return $occurrence.'['.$itemId.']';
    }

    /** @return list<string> */
    private function segments(string $path): array
    {
        $segments = array_values(array_filter(explode('.', trim($path)), static fn (string $part): bool => $part !== ''));
        if ($segments === []) {
            throw DataPlatformBadRequest::because('empty_document_field_path', 'Document field path cannot be empty.');
        }

        return $segments;
    }
}
