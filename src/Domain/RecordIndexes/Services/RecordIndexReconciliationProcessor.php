<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordIndexes\Services;

use LogicException;
use Polymorph\Platform\Domain\RecordIndexes\Support\RecordIndexReconciliationRequest;

final class RecordIndexReconciliationProcessor
{
    public function __construct(
        private readonly RecordIndexReconciler $reconciler,
        private readonly RecordIndexReconciliationRequestStore $requests,
    ) {}

    public function process(RecordIndexReconciliationRequest $request): void
    {
        match ($request->targetType) {
            RecordIndexReconciliationRequest::TARGET_SCHEMA => $this->reconciler->reconcileSchema($request->targetId),
            RecordIndexReconciliationRequest::TARGET_DEFINITION => $this->reconciler->reconcileDefinition($request->targetId),
            default => throw new LogicException("Unsupported record-index reconciliation target '{$request->targetType}'"),
        };

        $this->requests->deleteIfGeneration($request->id, $request->generation);
    }
}
