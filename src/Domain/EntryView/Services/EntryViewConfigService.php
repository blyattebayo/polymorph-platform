<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\EntryView\Services;

use Polymorph\Platform\Domain\EntryView\Core\Models\EntryViewConfig;
use Polymorph\Platform\Domain\EntryView\Infrastructure\EntryViewConfigRepository;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts\RecordDefinitionRepository;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Exceptions\RecordDefinitionNotFoundException;
use Polymorph\Platform\Domain\SchemaModel\Core\Exceptions\SchemaNotFoundException;
use Polymorph\Platform\Domain\SchemaModel\Infrastructure\Repositories\SchemaRepository;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

final class EntryViewConfigService
{
    public function __construct(
        private readonly EntryViewConfigRepository $configs,
        private readonly RecordDefinitionRepository $recordDefinitions,
        private readonly SchemaRepository $schemas,
        private readonly AppLogger $logger,
    ) {}

    public function show(int $recordDefinitionId, int $schemaId): ?EntryViewConfig
    {
        $this->requireTargets($recordDefinitionId, $schemaId);

        return $this->configs->find($recordDefinitionId, $schemaId);
    }

    public function update(
        int $recordDefinitionId,
        int $schemaId,
        int $version,
        string $document,
    ): EntryViewConfig {
        $this->requireTargets($recordDefinitionId, $schemaId);

        $config = $this->configs->save($recordDefinitionId, $schemaId, $version, $document);

        $this->logger->event($config->wasRecentlyCreated ? 'entry_view.created' : 'entry_view.updated', [
            'config_id' => $config->id,
            'record_definition_id' => $recordDefinitionId,
            'schema_id' => $schemaId,
        ]);

        return $config;
    }

    public function delete(int $recordDefinitionId, int $schemaId): void
    {
        $this->requireTargets($recordDefinitionId, $schemaId);
        $this->configs->delete($recordDefinitionId, $schemaId);
    }

    private function requireTargets(int $recordDefinitionId, int $schemaId): void
    {
        if (! $this->recordDefinitions->exists($recordDefinitionId)) {
            throw RecordDefinitionNotFoundException::byId($recordDefinitionId);
        }

        if (! $this->schemas->exists($schemaId)) {
            throw SchemaNotFoundException::byId($schemaId);
        }
    }
}
