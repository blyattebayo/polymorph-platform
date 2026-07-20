<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Services;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessSubjectProvider;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\PolicyRuntime;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts\RecordDefinitionRepository;
use Polymorph\Platform\Domain\Records\Core\Models\Record;
use Polymorph\Platform\Domain\Records\Support\RecordSchemaResolver;
use Polymorph\Platform\Domain\SchemaModel\Services\FieldAccessService;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

final class RecordReadProfileResolver
{
    public function __construct(
        private readonly RecordDefinitionRepository $recordDefinitionRepository,
        private readonly FieldAccessService $fieldAccessService,
        private readonly AccessSubjectProvider $subjectProvider,
        private readonly PolicyRuntime $policyRuntime,
    ) {}

    public function forDefinition(?UserIdentity $actor, int $recordDefinitionId, string $action = 'read'): RecordReadProfile
    {
        $definition = $this->recordDefinitionRepository->find($recordDefinitionId);
        $schemaId = $definition?->schema_id;

        return $this->forSchema($actor, is_int($schemaId) && $schemaId > 0 ? $schemaId : null, $action);
    }

    public function forRecord(?UserIdentity $actor, Record $record, string $action = 'read'): RecordReadProfile
    {
        return $this->forSchema($actor, RecordSchemaResolver::fromRecord($record), $action);
    }

    public function forSchema(?UserIdentity $actor, ?int $schemaId, string $action = 'read'): RecordReadProfile
    {
        if ($schemaId === null || $schemaId <= 0) {
            return new RecordReadProfile(null, [], true);
        }

        if (! $actor instanceof UserIdentity) {
            return new RecordReadProfile($schemaId, [], true);
        }

        $subjects = $this->subjectProvider->for($actor);
        $schemaResource = 'schema.'.$schemaId.'.fields';
        if ($this->policyRuntime->allows($subjects, $schemaResource, $action)) {
            return new RecordReadProfile($schemaId, [], true);
        }

        return new RecordReadProfile($schemaId, $this->fieldAccessService->visibleFieldPaths($actor, $schemaId, $action), false);
    }
}
