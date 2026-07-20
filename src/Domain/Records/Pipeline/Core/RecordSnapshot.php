<?php

namespace Polymorph\Platform\Domain\Records\Pipeline\Core;

use Polymorph\Platform\Domain\Records\Core\Models\Record;
use Polymorph\Platform\Domain\Records\Support\RecordDataNormalizer;
use Polymorph\Platform\Domain\Records\Support\RecordSystemFields;

/**
 * Immutable snapshot of record state at specific revision
 * Decouples pipeline logic from Eloquent model
 */
final class RecordSnapshot
{
    public function __construct(
        public readonly RecordId $id,
        public readonly RecordRevision $revision,
        public readonly int $recordDefinitionId,
        public readonly array $dataJson,
        public readonly bool $isDeleted,
    ) {}

    public static function fromModel(Record $record): self
    {
        $dataJson = RecordDataNormalizer::fromMixed($record->data_json);

        return new self(
            id: RecordId::fromInt($record->id),
            revision: RecordRevision::fromInt($record->revision),
            recordDefinitionId: $record->record_definition_id,
            dataJson: $dataJson,
            isDeleted: RecordSystemFields::isDeleted($dataJson),
        );
    }

    public function withNextRevision(): self
    {
        return new self(
            id: $this->id,
            revision: $this->revision->next(),
            recordDefinitionId: $this->recordDefinitionId,
            dataJson: $this->dataJson,
            isDeleted: $this->isDeleted,
        );
    }
}
