<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Infrastructure\Extractors;

use Polymorph\Platform\Support\Json\PathValueResolver;

/**
 * Extracts referenced record ids from record payload data.
 */
final class RecordRefExtractor
{
    public function __construct(
        private readonly ?PathValueResolver $valueResolver = null,
    ) {}

    /**
     * Extracts all referenced record ids from ref fields.
     *
     * @param  array<string, mixed>  $recordData
     */
    public function extractRefRecordIds(array $recordData, array $refPaths): array
    {
        if ($refPaths === []) {
            return [];
        }

        $recordIds = [];

        foreach ($refPaths as $path) {
            $ids = $this->extractRefsFromPath($recordData, $path);
            $recordIds = array_merge($recordIds, $ids);
        }

        return array_values(array_unique(array_filter($recordIds)));
    }

    /**
     * Extracts ids from a single ref path.
     */
    private function extractRefsFromPath(array $data, string $path): array
    {
        $value = ($this->valueResolver ?? new PathValueResolver)->get($data, $path);

        if ($value === null) {
            return [];
        }

        return $this->normalizeRefValue($value);
    }

    /**
     * Normalizes a ref value to an array of ids.
     */
    private function normalizeRefValue(mixed $value): array
    {
        if (is_numeric($value)) {
            return [(int) $value];
        }

        if (is_array($value)) {
            return $this->collectNumericIds($value);
        }

        return [];
    }

    /**
     * Recursively collects numeric ids from nested arrays.
     *
     * @param  array<mixed>  $value
     * @return array<int>
     */
    private function collectNumericIds(array $value): array
    {
        $ids = [];

        foreach ($value as $item) {
            if (is_numeric($item)) {
                $ids[] = (int) $item;

                continue;
            }

            if (is_array($item)) {
                $ids = array_merge($ids, $this->collectNumericIds($item));
            }
        }

        return $ids;
    }
}
