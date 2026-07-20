<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Pipeline\Contexts;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\Records\Core\Models\Record;
use Polymorph\Platform\Domain\Records\Pipeline\Core\RecordChangeSet;
use Polymorph\Platform\Domain\Records\Pipeline\Core\RecordId;
use Polymorph\Platform\Domain\Records\Pipeline\Core\RecordSnapshot;
use Polymorph\Platform\PipelineCore\Locking\LockKey;
use Polymorph\Platform\PipelineCore\Runtime\LockableContext;

/**
 * Write-side context for record modification pipeline
 * Contains state that flows through write stages
 */
final class RecordWriteContext implements LockableContext
{
    private ?Record $record = null;

    private ?RecordDefinition $recordDefinition = null;

    private ?RecordSnapshot $snapshotBefore = null;

    private ?RecordSnapshot $snapshotAfter = null;

    private ?RecordChangeSet $changeSet = null;

    private readonly array $pendingPayload;

    private ?array $normalizedPayload = null;

    public function __construct(
        public readonly string $operationId,
        public readonly ?RecordId $recordId,
        public readonly ?int $actorId,
        array $pendingPayload = [],
    ) {
        $this->pendingPayload = $pendingPayload;
    }

    /**
     * @return array<string, mixed>
     */
    public function pendingPayload(): array
    {
        return $this->pendingPayload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function setNormalizedPayload(array $payload): void
    {
        $this->normalizedPayload = $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedPayload(): array
    {
        return $this->normalizedPayload ?? $this->pendingPayload;
    }

    public function requireRecordId(): RecordId
    {
        if ($this->recordId === null) {
            throw new \LogicException('Record ID is not available in context');
        }

        return $this->recordId;
    }

    public function hasRecord(): bool
    {
        return $this->record !== null;
    }

    public function setRecord(Record $record): void
    {
        $this->record = $record;
    }

    public function hasRecordDefinition(): bool
    {
        return $this->recordDefinition !== null;
    }

    public function setRecordDefinition(RecordDefinition $recordDefinition): void
    {
        $this->recordDefinition = $recordDefinition;
    }

    public function requireRecordDefinition(): RecordDefinition
    {
        if ($this->recordDefinition === null) {
            throw new \LogicException('Record definition is not loaded in context');
        }

        return $this->recordDefinition;
    }

    public function requireRecord(): Record
    {
        if (! $this->record) {
            throw new \LogicException('Record not loaded in context');
        }

        return $this->record;
    }

    public function getSnapshotBefore(): ?RecordSnapshot
    {
        return $this->snapshotBefore;
    }

    public function setSnapshotBefore(RecordSnapshot $snapshot): void
    {
        $this->snapshotBefore = $snapshot;
    }

    public function requireSnapshotBefore(): RecordSnapshot
    {
        return $this->snapshotBefore ?? throw new \RuntimeException('Snapshot before is not available in context');
    }

    public function getSnapshotAfter(): ?RecordSnapshot
    {
        return $this->snapshotAfter;
    }

    public function setSnapshotAfter(RecordSnapshot $snapshot): void
    {
        $this->snapshotAfter = $snapshot;
    }

    public function requireSnapshotAfter(): RecordSnapshot
    {
        return $this->snapshotAfter ?? throw new \RuntimeException('Snapshot after is not available in context');
    }

    public function getChangeSet(): RecordChangeSet
    {
        return $this->changeSet ?? RecordChangeSet::empty();
    }

    public function hasChangeSet(): bool
    {
        return $this->changeSet !== null;
    }

    public function setChangeSet(RecordChangeSet $changeSet): void
    {
        $this->changeSet = $changeSet;
    }

    public function getLockKey(): LockKey
    {
        $resourceId = $this->recordId?->value !== null
            ? (string) $this->recordId->value
            : 'create:'.$this->operationId;

        return new LockKey(
            resourceType: 'record',
            resourceId: $resourceId,
        );
    }
}
