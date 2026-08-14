<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\SdkBridge;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Control\DefinitionService;
use Polymorph\Platform\Domain\DataPlatform\Control\FieldSpecification;
use Polymorph\Platform\Domain\DataPlatform\Control\SchemaDraftService;
use Polymorph\Platform\Domain\DataPlatform\Control\SchemaLifecycleService;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformStateConflict;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition as CoreFieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Migration\MigrationClassification;
use Polymorph\Platform\Domain\DataPlatform\Migration\MigrationOperation;
use Polymorph\Platform\Domain\DataPlatform\Migration\MigrationPlanState;
use Polymorph\Platform\Domain\DataPlatform\Migration\SchemaMigrationRunner;
use Polymorph\Platform\Domain\DataPlatform\Migration\SchemaMigrationService;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaCatalog;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaState;
use Polymorph\Platform\SharedKernel\Ownership\ResourceOwner;
use Polymorph\Platform\SharedKernel\Ownership\ResourceOwnershipService;
use Polymorph\Platform\SharedKernel\Ownership\ResourceType;
use Polymorph\Platform\Support\Validation\ValidationConstraints;
use Polymorph\Sdk\Data\DefinitionRef;
use Polymorph\Sdk\Data\FieldDefinition;
use Polymorph\Sdk\Data\SchemaSpec;

/** Idempotently provisions extension-owned definitions through control-plane use cases. */
final class ExtensionDefinitionProvisioner
{
    public function __construct(
        private readonly DefinitionService $definitions,
        private readonly SchemaDraftService $drafts,
        private readonly SchemaLifecycleService $lifecycle,
        private readonly SchemaCatalog $schemas,
        private readonly SchemaMigrationService $migrations,
        private readonly SchemaMigrationRunner $migrationRunner,
        private readonly ResourceOwnershipService $ownership,
    ) {}

    public function ensure(string $extensionId, string $entity, SchemaSpec $spec): DefinitionRef
    {
        $entity = $this->entityName($entity);
        if ($spec->fields === []) {
            throw DataPlatformBadRequest::because(
                'extension_definition_has_no_fields',
                "Definition '{$extensionId}.{$entity}' must declare at least one field.",
                ['extension_id' => $extensionId, 'entity' => $entity],
            );
        }

        // Provisioning is synchronous and externally idempotent. Keep every
        // lifecycle transition, migration write, publication, and ownership
        // update in one transaction so a failed batch leaves no unreachable
        // validating/backfilling version for the next ensure call.
        return DB::transaction(function () use ($extensionId, $entity, $spec): DefinitionRef {
            $code = ExtensionStorageKey::schemaCode($extensionId, $entity);
            $definition = $this->schemas->findDefinitionByCode($code);
            if ($definition === null) {
                $created = $this->definitions->create(
                    $code,
                    trim($spec->name) !== '' ? trim($spec->name) : $entity,
                    array_map($this->sdkField(...), $spec->fields),
                    ['owner' => ['type' => 'extension', 'id' => $extensionId]],
                );
                $definitionId = $created->definitionId;
                $versionId = $created->schemaVersionId;
                $this->lifecycle->transition($versionId, SchemaState::Validating);
                $this->lifecycle->transition($versionId, SchemaState::Published);
            } else {
                $definitionId = (int) $definition['id'];
                $versionId = (string) ($definition['current_schema_version_id'] ?? '');
                if ($versionId === '') {
                    throw DataPlatformStateConflict::because(
                        'extension_definition_has_no_published_schema',
                        "Definition '{$code}' has no published schema.",
                        ['definition_code' => $code],
                    );
                }
                $versionId = $this->addMissingFields($definitionId, $versionId, $spec->fields, $code);
            }

            $this->ownership->set(
                ResourceType::RECORD_DEFINITION,
                $definitionId,
                ResourceOwner::plugin($extensionId),
            );

            return new DefinitionRef($definitionId, $versionId, $entity);
        }, 3);
    }

    /** @param list<FieldDefinition> $fields */
    private function addMissingFields(int $definitionId, string $currentVersionId, array $fields, string $code): string
    {
        $existing = $this->schemas->fieldsByPath($currentVersionId);
        $missing = array_values(array_filter(
            $fields,
            static fn (FieldDefinition $field): bool => ! isset($existing[$field->name]),
        ));
        if ($missing === []) {
            return $currentVersionId;
        }
        if ($this->schemas->hasUnfinishedSchemaWork($definitionId)) {
            throw DataPlatformStateConflict::because(
                'extension_definition_migration_in_progress',
                "Definition '{$code}' already has unfinished schema work.",
                ['definition_code' => $code],
            );
        }

        $draftId = $this->drafts->create($definitionId);
        $specifications = array_map($this->existingField(...), array_values($existing), array_keys(array_values($existing)));
        array_push($specifications, ...array_map($this->sdkField(...), $missing));
        $this->drafts->replaceFields($draftId, $specifications);
        $this->lifecycle->transition($draftId, SchemaState::Validating);
        $this->lifecycle->transition($draftId, SchemaState::Backfilling);
        $planId = $this->migrations->createPlan(
            $currentVersionId,
            $draftId,
            MigrationClassification::Additive,
            array_map(static fn (FieldDefinition $field): MigrationOperation => MigrationOperation::fromArray([
                'op' => 'add_field',
                'path' => $field->name,
                'default' => null,
            ]), $missing),
        );

        do {
            $run = $this->migrationRunner->runBatch($planId, 200);
        } while ($run['remaining'] > 0 && $run['state'] === MigrationPlanState::Running->value);
        if ($run['state'] !== MigrationPlanState::Completed->value) {
            throw DataPlatformStateConflict::because(
                'extension_schema_migration_failed',
                "Schema migration for '{$code}' contains invalid records.",
                ['definition_code' => $code, 'failed_records' => $run['failed']],
            );
        }
        $this->lifecycle->transition($draftId, SchemaState::Published);

        return $draftId;
    }

    private function existingField(CoreFieldDefinition $field, int $position): FieldSpecification
    {
        return FieldSpecification::fromArray([
            'field_id' => $field->id,
            'key' => $field->path,
            'path' => $field->path,
            'name' => $field->name,
            'type' => $field->typeName(),
            'cardinality' => $field->cardinality->value,
            'is_system' => $field->system,
            'position' => $position,
            'projection_version' => $field->projectionVersion,
            'constraints' => $field->constraints,
            'metadata' => $field->metadata,
            'parent_field_id' => $field->parentId,
        ]);
    }

    private function sdkField(FieldDefinition $field): FieldSpecification
    {
        $constraints = $field->rules;
        if (isset($constraints['in']) && is_array($constraints['in'])) {
            $constraints['enum'] = array_values($constraints['in']);
            unset($constraints['in']);
        }
        if (isset($constraints['regex'])) {
            $constraints['pattern'] = $constraints['regex'];
            unset($constraints['regex']);
        }
        if (in_array($field->type->value, ['string', 'text'], true)) {
            if (isset($constraints['min'])) {
                $constraints['min_length'] = $constraints['min'];
                unset($constraints['min']);
            }
            if (isset($constraints['max'])) {
                $constraints['max_length'] = $constraints['max'];
                unset($constraints['max']);
            }
        }

        return FieldSpecification::fromArray([
            'key' => $field->name,
            'path' => $field->name,
            'name' => $field->name,
            'type' => $field->type->value,
            'cardinality' => $field->cardinality->value,
            'position' => $field->sortOrder,
            'constraints' => $constraints,
            'metadata' => ['indexed' => $field->indexed, 'unique' => $field->unique],
        ]);
    }

    private function entityName(string $entity): string
    {
        $entity = trim($entity);
        if (! ValidationConstraints::slug()->matches($entity)) {
            throw DataPlatformBadRequest::because(
                'invalid_extension_entity_name',
                'Extension definition entity must be a slug.',
                ['entity' => $entity],
            );
        }

        return $entity;
    }
}
