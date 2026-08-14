<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\SdkBridge;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Control\DefinitionMetadataService;
use Polymorph\Platform\Domain\DataPlatform\Control\DefinitionService;
use Polymorph\Platform\Domain\DataPlatform\Control\FieldSpecification;
use Polymorph\Platform\Domain\DataPlatform\Control\SchemaDraftService;
use Polymorph\Platform\Domain\DataPlatform\Control\SchemaLifecycleService;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformStateConflict;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition as CoreFieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Migration\MigrationClassification;
use Polymorph\Platform\Domain\DataPlatform\Migration\MigrationPlanState;
use Polymorph\Platform\Domain\DataPlatform\Migration\SchemaMigrationRunner;
use Polymorph\Platform\Domain\DataPlatform\Migration\SchemaMigrationService;
use Polymorph\Platform\Domain\DataPlatform\Schema\CompiledSchemaTree;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaCatalog;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaState;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaStorage;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;
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
        private readonly DefinitionMetadataService $definitionMetadata,
        private readonly SchemaDraftService $drafts,
        private readonly SchemaLifecycleService $lifecycle,
        private readonly SchemaCatalog $schemas,
        private readonly SchemaMigrationService $migrations,
        private readonly SchemaMigrationRunner $migrationRunner,
        private readonly ResourceOwnershipService $ownership,
        private readonly DatabaseJson $json,
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
            $repairOwnerMetadata = false;
            if ($definition === null) {
                $created = $this->definitions->create(
                    $code,
                    trim($spec->name) !== '' ? trim($spec->name) : $entity,
                    array_map($this->sdkField(...), $spec->fields),
                    ['owner' => ['type' => 'plugin', 'id' => $extensionId]],
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
                $metadata = $this->json->decodeMap(
                    $definition['metadata'] ?? null,
                    SchemaStorage::DEFINITION_METADATA_CONTEXT,
                );
                $owner = $metadata['owner'] ?? null;
                $repairOwnerMetadata = ! is_array($owner)
                    || ($owner['type'] ?? null) !== 'plugin'
                    || ($owner['id'] ?? null) !== $extensionId;
            }

            if ($repairOwnerMetadata) {
                // Definition metadata is part of the public control-plane contract.
                // Definitions created by pre-6.1 hosts used the legacy "extension"
                // discriminator; repair those once without rewriting healthy rows.
                $this->definitionMetadata->update($definitionId, null, [
                    'owner' => ['type' => 'plugin', 'id' => $extensionId],
                ]);
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
        $sdkByPath = $this->sdkFieldsByPath($fields);
        $missing = array_values(array_filter(
            $sdkByPath,
            static fn (FieldDefinition $field, string $path): bool => ! isset($existing[$path]),
            ARRAY_FILTER_USE_BOTH,
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
        $specifications = $this->mergedSpecifications($this->schemas->tree($currentVersionId), $fields);
        $this->drafts->replaceFields($draftId, $specifications);
        $this->lifecycle->transition($draftId, SchemaState::Validating);
        $this->lifecycle->transition($draftId, SchemaState::Backfilling);
        $planId = $this->migrations->createPlan(
            $currentVersionId,
            $draftId,
            MigrationClassification::Additive,
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

    /** @param list<FieldSpecification> $children */
    private function existingField(CoreFieldDefinition $field, array $children): FieldSpecification
    {
        return FieldSpecification::fromArray([
            'field_id' => $field->id,
            'name' => $field->name,
            'type' => $field->typeName(),
            'cardinality' => $field->cardinality->value,
            'is_system' => $field->system,
            'position' => $field->position,
            'projection_version' => $field->projectionVersion,
            'constraints' => $field->constraints,
            'metadata' => $field->metadata,
            'children' => array_map(fn (FieldSpecification $child): array => $this->specificationArray($child), $children),
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
            'name' => $field->name,
            'type' => $field->type->value,
            'cardinality' => $field->cardinality->value,
            'position' => $field->sortOrder,
            'constraints' => $constraints,
            'metadata' => ['indexed' => $field->indexed, 'unique' => $field->unique],
            'children' => array_map(
                fn (FieldDefinition $child): array => $this->specificationArray($this->sdkField($child)),
                $field->children,
            ),
        ]);
    }

    /** @param list<FieldDefinition> $sdkRoots @return list<FieldSpecification> */
    private function mergedSpecifications(CompiledSchemaTree $tree, array $sdkRoots): array
    {
        $sdkByParent = [];
        $walkSdk = function (array $fields, string $parentPath) use (&$walkSdk, &$sdkByParent): void {
            foreach ($fields as $field) {
                $sdkByParent[$parentPath][] = $field;
                $path = $parentPath === '$' ? $field->name : $parentPath.'.'.$field->name;
                $walkSdk($field->children, $path);
            }
        };
        $walkSdk($sdkRoots, '$');

        $mergeLevel = function (?CoreFieldDefinition $parent, string $parentPath) use (&$mergeLevel, $tree, $sdkByParent): array {
            $existingChildren = $parent === null ? $tree->roots() : $tree->childrenOf($parent);
            $specifications = [];
            $existingNames = [];
            foreach ($existingChildren as $child) {
                $existingNames[$child->name] = true;
                $specifications[] = $this->existingField(
                    $child,
                    $mergeLevel($child, $child->path),
                );
            }
            foreach ($sdkByParent[$parentPath] ?? [] as $sdkField) {
                if (! isset($existingNames[$sdkField->name])) {
                    $specifications[] = $this->sdkField($sdkField);
                }
            }

            return $specifications;
        };

        return $mergeLevel(null, '$');
    }

    /** @param list<FieldDefinition> $fields @return array<string,FieldDefinition> */
    private function sdkFieldsByPath(array $fields): array
    {
        $result = [];
        $walk = function (array $nodes, string $parentPath) use (&$walk, &$result): void {
            foreach ($nodes as $field) {
                $path = $parentPath === '' ? $field->name : $parentPath.'.'.$field->name;
                $result[$path] = $field;
                $walk($field->children, $path);
            }
        };
        $walk($fields, '');

        return $result;
    }

    /** @return list<array<string,mixed>> */
    private function specificationChildren(FieldSpecification $specification): array
    {
        return array_map(fn (FieldSpecification $child): array => $this->specificationArray($child), $specification->children);
    }

    /** @return array<string,mixed> */
    private function specificationArray(FieldSpecification $specification): array
    {
        return [
            'field_id' => $specification->fieldId,
            'name' => $specification->name,
            'type' => $specification->type,
            'cardinality' => $specification->cardinality->value,
            'is_system' => $specification->system,
            'position' => $specification->position,
            'projection_version' => $specification->projectionVersion,
            'constraints' => $specification->constraints,
            'metadata' => $specification->metadata,
            'children' => $this->specificationChildren($specification),
        ];
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
