<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Services;

final class PathTreeFilter
{
    /**
     * @param  array<string,mixed>  $dataJson
     * @param  array<int,string>  $allowedPaths
     * @return array<string,mixed>
     */
    public function filterVisibleData(array $dataJson, array $allowedPaths): array
    {
        $allowedPathSet = array_fill_keys($allowedPaths, true);
        if ($allowedPathSet === []) {
            return [];
        }

        $items = $this->collectLeafItems($dataJson);
        foreach ($items as $item) {
            $normalized = $item['normalized'];
            if ($normalized === '' || $this->isPathAllowed($normalized, $allowedPathSet)) {
                continue;
            }

            $this->unsetByActualPath($dataJson, $item['actual']);
        }

        return $this->pruneEmptyArrays($dataJson);
    }

    /**
     * @param  array<string,mixed>  $dataJson
     * @param  array<int,string>  $allowedPaths
     * @return array<int,string>
     */
    public function forbiddenWritePaths(array $dataJson, array $allowedPaths): array
    {
        $allowedPathSet = array_fill_keys($allowedPaths, true);
        $items = $this->collectLeafItems($dataJson);
        $forbidden = [];

        foreach ($items as $item) {
            $normalized = $item['normalized'];
            if ($normalized === '' || $this->isPathAllowed($normalized, $allowedPathSet)) {
                continue;
            }

            $forbidden[] = $normalized;
        }

        return array_values(array_unique($forbidden));
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  array<int,string|int>  $actualPath
     * @param  array<int,string>  $logicalPath
     * @return array<int,array{actual:array<int,string|int>,normalized:string}>
     */
    private function collectLeafItems(array $data, array $actualPath = [], array $logicalPath = []): array
    {
        $items = [];

        foreach ($data as $key => $value) {
            $actual = [...$actualPath, $key];
            $segments = $this->expandLogicalSegments($key);
            $nextLogical = [...$logicalPath, ...$segments];

            if (is_array($value)) {
                $items = [...$items, ...$this->collectLeafItems($value, $actual, $nextLogical)];

                continue;
            }

            $items[] = [
                'actual' => $actual,
                'normalized' => $this->normalizeLogicalPath($nextLogical),
            ];
        }

        return $items;
    }

    /**
     * @return string[]
     */
    private function expandLogicalSegments(string|int $key): array
    {
        if (is_int($key) || ctype_digit((string) $key)) {
            return [(string) $key];
        }

        $parts = array_values(array_filter(explode('.', (string) $key), static fn (string $part): bool => $part !== ''));

        return $parts === [] ? [(string) $key] : $parts;
    }

    /**
     * @param  string[]  $segments
     */
    private function normalizeLogicalPath(array $segments): string
    {
        $filtered = array_values(array_filter(
            $segments,
            static fn (string $segment): bool => ! ctype_digit($segment)
        ));

        return implode('.', $filtered);
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  array<int,string|int>  $actualPath
     */
    private function unsetByActualPath(array &$data, array $actualPath): void
    {
        if ($actualPath === []) {
            return;
        }

        $current = &$data;
        $lastIndex = count($actualPath) - 1;

        foreach ($actualPath as $index => $segment) {
            if ($index === $lastIndex) {
                if (is_array($current) && array_key_exists($segment, $current)) {
                    unset($current[$segment]);
                }

                return;
            }

            if (! is_array($current) || ! array_key_exists($segment, $current) || ! is_array($current[$segment])) {
                return;
            }

            $current = &$current[$segment];
        }
    }

    /**
     * @param  array<string,mixed>  $value
     * @return array<string,mixed>
     */
    private function pruneEmptyArrays(array $value): array
    {
        foreach ($value as $key => $item) {
            if (! is_array($item)) {
                continue;
            }

            $value[$key] = $this->pruneEmptyArrays($item);
            if ($value[$key] === []) {
                unset($value[$key]);
            }
        }

        return $value;
    }

    /**
     * @param  array<string,true>  $allowedPathSet
     */
    private function isPathAllowed(string $normalizedPath, array $allowedPathSet): bool
    {
        if (array_key_exists($normalizedPath, $allowedPathSet)) {
            return true;
        }

        foreach ($allowedPathSet as $allowedPath => $_) {
            if ($allowedPath !== '' && str_starts_with($normalizedPath, $allowedPath.'.')) {
                return true;
            }
        }

        return false;
    }
}
