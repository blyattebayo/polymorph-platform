<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Services;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\SchemaModel\Services\FieldAccessService;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ThrowsErrors;

final class RecordWriteAccessService
{
    use ThrowsErrors;

    public function __construct(
        private readonly FieldAccessService $fieldAccessService,
    ) {}

    /**
     * @param  array<string,mixed>  $dataJson
     */
    public function assertCanWriteRecordDefinitionPayload(User $actor, RecordDefinition $recordDefinition, array $dataJson): void
    {
        $schemaId = $recordDefinition->schema_id;
        if (! is_int($schemaId) || $schemaId <= 0) {
            return;
        }

        $this->assertNoForbiddenWritePaths($actor, $schemaId, $dataJson);
    }

    /**
     * @param  array<string,mixed>  $dataJson
     */
    private function assertNoForbiddenWritePaths(User $actor, int $schemaId, array $dataJson): void
    {
        $forbidden = $this->fieldAccessService->forbiddenWritePaths($actor, $schemaId, $dataJson);

        if ($forbidden === []) {
            return;
        }

        $this->throwError(
            ErrorCode::FORBIDDEN,
            'You are not allowed to write one or more schema fields.',
            ['forbidden_paths' => $forbidden],
        );
    }
}
