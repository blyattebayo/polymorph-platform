<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Polymorph\Platform\Domain\DataPlatform\Control\DataControlReadModel;
use Polymorph\Platform\Domain\DataPlatform\Control\DefinitionDeleteService;
use Polymorph\Platform\Domain\DataPlatform\Control\DefinitionFormConfigService;
use Polymorph\Platform\Domain\DataPlatform\Control\DefinitionMetadataService;
use Polymorph\Platform\Domain\DataPlatform\Control\DefinitionService;
use Polymorph\Platform\Domain\DataPlatform\Control\FieldSpecification;
use Polymorph\Platform\Domain\DataPlatform\Control\SchemaDraftService;
use Polymorph\Platform\Domain\DataPlatform\Control\SchemaLifecycleService;
use Polymorph\Platform\Domain\DataPlatform\Migration\MigrationClassification;
use Polymorph\Platform\Domain\DataPlatform\Migration\MigrationOperation;
use Polymorph\Platform\Domain\DataPlatform\Migration\SchemaMigrationRunner;
use Polymorph\Platform\Domain\DataPlatform\Migration\SchemaMigrationService;
use Polymorph\Platform\Domain\DataPlatform\Projection\DisplayTemplateCompiler;
use Polymorph\Platform\Domain\DataPlatform\Projection\ProjectionRebuilder;
use Polymorph\Platform\Domain\DataPlatform\Projection\ProjectionRebuildScheduler;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaState;

/** Versioned schema editor and operational evidence API. */
final readonly class DataControlController
{
    public function __construct(
        private DefinitionService $definitions,
        private DefinitionMetadataService $definitionMetadata,
        private DefinitionDeleteService $definitionDeletes,
        private DefinitionFormConfigService $formConfigs,
        private DataControlReadModel $readModel,
        private SchemaDraftService $drafts,
        private SchemaLifecycleService $schemaLifecycle,
        private SchemaMigrationService $migrations,
        private SchemaMigrationRunner $migrationRunner,
        private ProjectionRebuilder $projections,
        private DisplayTemplateCompiler $displayTemplates,
        private ProjectionRebuildScheduler $projectionRebuilds,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->readModel->definitions()]);
    }

    public function show(int $definitionId): JsonResponse
    {
        return response()->json(['data' => $this->readModel->definition($definitionId)]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'code' => ['required', 'string', 'max:128', 'regex:'.DefinitionService::CODE_PATTERN],
            'name' => ['required', 'string', 'max:255'],
            'metadata' => ['sometimes', 'array'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['array'],
        ]);
        $created = $this->definitions->create(
            $payload['code'],
            $payload['name'],
            $this->fieldSpecifications($payload['fields'] ?? []),
            $payload['metadata'] ?? [],
        );

        return response()->json(['data' => $this->readModel->definition($created->definitionId)], 201);
    }

    public function updateDefinition(Request $request, int $definitionId): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'min:1', 'max:255'],
            'metadata' => ['sometimes', 'array'],
        ]);
        $this->definitionMetadata->update(
            $definitionId,
            isset($payload['name']) ? (string) $payload['name'] : null,
            $payload['metadata'] ?? [],
        );

        return response()->json(['data' => $this->readModel->definition($definitionId)]);
    }

    public function destroyDefinition(int $definitionId): JsonResponse
    {
        $this->definitionDeletes->delete($definitionId);

        return response()->json(status: 204);
    }

    public function validateDisplayTemplate(Request $request, int $definitionId): JsonResponse
    {
        $payload = $request->validate([
            'display_template' => ['present', 'nullable', 'string', 'max:5000'],
        ]);
        $compiled = $this->displayTemplates->compile($definitionId, $payload['display_template']);

        return response()->json(['data' => [
            'valid' => true,
            'template_hash' => $compiled->hash,
        ]]);
    }

    public function showFormConfig(int $definitionId): JsonResponse
    {
        $config = $this->formConfigs->get($definitionId);

        return response()->json($config === [] ? new \stdClass : $config);
    }

    public function updateFormConfig(Request $request, int $definitionId): JsonResponse
    {
        $payload = $request->validate(['config_json' => ['required', 'array']]);
        $saved = $this->formConfigs->update($definitionId, $payload['config_json']);

        return response()->json(['data' => $saved]);
    }

    public function createDraft(int $definitionId): JsonResponse
    {
        $schemaVersionId = $this->drafts->create($definitionId);

        return response()->json(['data' => $this->readModel->version($schemaVersionId)], 201);
    }

    public function replaceFields(Request $request, string $schemaVersionId): JsonResponse
    {
        $payload = $request->validate([
            'fields' => ['required', 'array'],
            'fields.*' => ['array'],
        ]);
        $this->drafts->replaceFields($schemaVersionId, $this->fieldSpecifications($payload['fields']));

        return response()->json(['data' => $this->readModel->version($schemaVersionId)]);
    }

    public function transition(Request $request, string $schemaVersionId): JsonResponse
    {
        $payload = $request->validate([
            'state' => ['required', 'string', 'in:'.implode(',', SchemaState::values())],
        ]);
        $this->schemaLifecycle->transition($schemaVersionId, SchemaState::from($payload['state']));

        return response()->json(['data' => $this->readModel->version($schemaVersionId)]);
    }

    public function createMigrationPlan(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'from_schema_version_id' => ['required', 'string', 'size:26'],
            'to_schema_version_id' => ['required', 'string', 'size:26'],
            'classification' => ['required', 'string', 'in:'.implode(',', MigrationClassification::values())],
            'operations' => ['required', 'array'],
            'operations.*' => ['array'],
        ]);
        $id = $this->migrations->createPlan(
            $payload['from_schema_version_id'],
            $payload['to_schema_version_id'],
            MigrationClassification::from($payload['classification']),
            array_map(
                static fn (array $operation): MigrationOperation => MigrationOperation::fromArray($operation),
                $payload['operations'],
            ),
        );

        return response()->json(['data' => $this->readModel->migrationPlan($id)], 201);
    }

    public function runMigration(Request $request, string $planId): JsonResponse
    {
        $payload = $request->validate([
            'batch_size' => ['sometimes', 'integer', 'between:1,1000'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);
        $result = $this->migrationRunner->runBatch(
            $planId,
            (int) ($payload['batch_size'] ?? 200),
            (bool) ($payload['dry_run'] ?? false),
        );

        return response()->json(['data' => $result, 'plan' => $this->readModel->migrationPlan($planId)]);
    }

    public function rebuildProjections(Request $request, int $definitionId): JsonResponse
    {
        $payload = $request->validate([
            'batch_size' => ['sometimes', 'integer', 'between:1,1000'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);

        $batchSize = (int) ($payload['batch_size'] ?? 200);
        if ((bool) ($payload['dry_run'] ?? false)) {
            $batch = $this->projections->rebuildDefinitionBatch($definitionId, 0, $batchSize, true);

            return response()->json(['data' => [
                'processed' => $batch->processed,
                'changed' => count($batch->changedRecordIds),
                'next_after_record_id' => $batch->lastRecordId,
                'has_more' => $batch->mayHaveMore,
                'dry_run' => true,
            ]]);
        }

        $this->projectionRebuilds->scheduleDefinition($definitionId);

        return response()->json(['data' => ['scheduled' => true]], 202);
    }

    /** @param array<array-key,array<string,mixed>> $fields @return list<FieldSpecification> */
    private function fieldSpecifications(array $fields): array
    {
        return array_map(
            static fn (array $field, int $position): FieldSpecification => FieldSpecification::fromArray($field, $position),
            array_values($fields),
            array_keys(array_values($fields)),
        );
    }
}
