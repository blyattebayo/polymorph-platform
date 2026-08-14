<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Http;

use stdClass;

/** Stabilizes map-shaped fields before PHP's JSON list/object inference. */
final class DataRecordResponsePresenter
{
    /** @param array<string,mixed>|null $record @return array<string,mixed>|null */
    public function record(?array $record): ?array
    {
        if ($record === null) {
            return null;
        }
        $record['relationships'] = $this->map((array) ($record['relationships'] ?? []));

        return $record;
    }

    /** @param array<string,mixed> $included @return array{records:stdClass,media:stdClass} */
    public function included(array $included): array
    {
        $records = [];
        foreach ((array) ($included['records'] ?? []) as $id => $record) {
            if (is_array($record)) {
                $records[(string) $id] = $this->record($record);
            }
        }

        return [
            'records' => $this->map($records),
            'media' => $this->map((array) ($included['media'] ?? [])),
        ];
    }

    /** @param array<string,array<string,mixed>> $records */
    public function records(array $records): stdClass
    {
        $presented = [];
        foreach ($records as $id => $record) {
            $presented[(string) $id] = $this->record($record);
        }

        return $this->map($presented);
    }

    /** @param array<string,mixed> $values */
    private function map(array $values): stdClass
    {
        return (object) $values;
    }
}
