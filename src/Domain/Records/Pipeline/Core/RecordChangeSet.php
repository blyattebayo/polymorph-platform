<?php

namespace Polymorph\Platform\Domain\Records\Pipeline\Core;

/**
 * Represents computed difference between record states
 * Built by BuildRecordChangeSetStep and used for shouldRun() decisions
 */
final class RecordChangeSet
{
    /**
     * @param bool $isNoop Whether the pending change has no effective impact
     * @param array<string> $changedFields Field names that changed
     * @param array{added: array<int>, removed: array<int>} $refDiff Reference IDs diff
     */
    public function __construct(
        public readonly bool $isNoop,
        public readonly array $changedFields,
        public readonly array $refDiff,
    ) {}

    public static function empty(): self
    {
        return new self(
            isNoop: true,
            changedFields: [],
            refDiff: ['added' => [], 'removed' => []],
        );
    }

    public function isNoop(): bool
    {
        return $this->isNoop;
    }
}
