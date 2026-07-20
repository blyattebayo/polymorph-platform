<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Infrastructure\Extractors;

use Polymorph\Platform\Domain\Records\Core\Models\Record;
use Polymorph\Platform\Domain\Records\Support\RecordDataNormalizer;
use Polymorph\Platform\Support\Json\PathValueResolver;

/**
 * Extracts related media ids from record payload data.
 */
final class RecordMediaExtractor
{
    public function __construct(
        private readonly ?PathValueResolver $valueResolver = null,
    ) {}

    /**
     * Extracts all media ids from media fields.
     */
    public function extractMediaIds(Record $record, array $mediaPaths): array
    {
        if ($mediaPaths === []) {
            return [];
        }

        $mediaIds = [];
        $recordData = RecordDataNormalizer::fromMixed($record->data_json);

        foreach ($mediaPaths as $path) {
            $ids = $this->extractMediaFromPath($recordData, $path);
            $mediaIds = array_merge($mediaIds, $ids);
        }

        return array_values(array_unique(array_filter($mediaIds)));
    }

    /**
     * Extracts media ids from a single media path.
     */
    private function extractMediaFromPath(array $data, string $path): array
    {
        $value = ($this->valueResolver ?? new PathValueResolver)->get($data, $path);

        if ($value === null) {
            return [];
        }

        return $this->normalizeMediaValue($value);
    }

    /**
     * Normalizes a media value to an array of ids.
     */
    private function normalizeMediaValue(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            return [$value];
        }

        if (is_array($value)) {
            $ids = [];
            foreach ($value as $item) {
                if (is_string($item) && $item !== '') {
                    $ids[] = $item;
                }
            }

            return $ids;
        }

        return [];
    }
}
