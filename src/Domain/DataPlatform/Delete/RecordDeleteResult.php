<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Delete;

use Polymorph\Platform\Domain\DataPlatform\Write\IdempotencyResult;

final readonly class RecordDeleteResult implements IdempotencyResult
{
    /** @param list<int> $deletedRecordIds */
    public function __construct(
        public int $recordId,
        public int $revision,
        public array $deletedRecordIds,
        public bool $replayed = false,
    ) {}

    /** @return array{record_id:int,revision:int,deleted_record_ids:list<int>,replayed:bool} */
    public function toArray(): array
    {
        return [
            'record_id' => $this->recordId,
            'revision' => $this->revision,
            'deleted_record_ids' => $this->deletedRecordIds,
            'replayed' => $this->replayed,
        ];
    }
}
