<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordIndexes\Support;

use LogicException;

final readonly class RecordIndexReconciliationRequest
{
    public const TARGET_SCHEMA = 'schema';

    public const TARGET_DEFINITION = 'definition';

    public function __construct(
        public int $id,
        public string $targetType,
        public int $targetId,
        public int $generation,
    ) {
        if (! in_array($targetType, [self::TARGET_SCHEMA, self::TARGET_DEFINITION], true)) {
            throw new LogicException("Unsupported record-index reconciliation target '{$targetType}'");
        }
        if ($id <= 0 || $targetId <= 0 || $generation <= 0) {
            throw new LogicException('Record-index reconciliation request identifiers must be positive');
        }
    }
}
