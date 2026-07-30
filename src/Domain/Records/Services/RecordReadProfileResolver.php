<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Services;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts\RecordDefinitionRepository;
use Polymorph\Platform\Domain\Records\Core\Models\Record;
use Polymorph\Platform\Domain\Records\Support\RecordSchemaResolver;
use Polymorph\Platform\Domain\SchemaModel\Access\SchemaFieldResources;
use Polymorph\Platform\Domain\SchemaModel\Services\FieldAccessService;
use Polymorph\Platform\SharedKernel\Access\AccessGate;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

final class RecordReadProfileResolver
{
    public function __construct(
        private readonly RecordDefinitionRepository $recordDefinitionRepository,
        private readonly FieldAccessService $fieldAccessService,
        private readonly AccessGate $gate,
    ) {}

    public function forDefinition(?UserIdentity $actor, int $recordDefinitionId, string $action = CapabilityCatalog::ACTION_READ): RecordReadProfile
    {
        $definition = $this->recordDefinitionRepository->find($recordDefinitionId);
        $schemaId = $definition?->schema_id;

        return $this->forSchema($actor, is_int($schemaId) && $schemaId > 0 ? $schemaId : null, $action);
    }

    public function forRecord(?UserIdentity $actor, Record $record, string $action = CapabilityCatalog::ACTION_READ): RecordReadProfile
    {
        return $this->forSchema($actor, RecordSchemaResolver::fromRecord($record), $action);
    }

    public function forSchema(?UserIdentity $actor, ?int $schemaId, string $action = CapabilityCatalog::ACTION_READ): RecordReadProfile
    {
        if ($schemaId === null || $schemaId <= 0) {
            return new RecordReadProfile(null, [], true);
        }

        // Fail-closed: нет актора — не видно НИ ОДНОГО поля. Раньше здесь было
        // «видно всё», и любой будущий вызов без аутентификации (все нынешние
        // HTTP-пути передают актора из DI) молча раскрывал бы поля, которые
        // политики прячут от каждого реального пользователя.
        if (! $actor instanceof UserIdentity) {
            return new RecordReadProfile($schemaId, [], false);
        }

        // Тот же формат ресурса, что у FieldAccessService, — из одного источника:
        // молчаливое расхождение строк здесь означало бы «видно всё».
        if ($this->gate->allows($actor, SchemaFieldResources::root($schemaId), $action)) {
            return new RecordReadProfile($schemaId, [], true);
        }

        return new RecordReadProfile($schemaId, $this->fieldAccessService->visibleFieldPaths($actor, $schemaId, $action), false);
    }
}
