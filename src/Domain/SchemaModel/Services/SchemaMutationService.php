<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Polymorph\Platform\Domain\SchemaModel\Core\Exceptions\DuplicateSchemaCodeException;
use Polymorph\Platform\Domain\SchemaModel\Core\Exceptions\SchemaInUseException;
use Polymorph\Platform\Domain\SchemaModel\Core\Exceptions\SchemaNotFoundException;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Core\SystemFields\SystemFieldPolicy;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\Cardinality;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldType;
use Polymorph\Platform\Domain\SchemaModel\Events\SchemaChanged;
use Polymorph\Platform\Domain\SchemaModel\Infrastructure\Repositories\FieldRepository;
use Polymorph\Platform\Domain\SchemaModel\Infrastructure\Repositories\SchemaRepository;
use Polymorph\Platform\PipelineCore\Locking\LockKey;
use Polymorph\Platform\PipelineCore\Locking\LockManager;
use Polymorph\Platform\SharedKernel\Ownership\ResourceOwner;
use Polymorph\Platform\SharedKernel\Ownership\ResourceOwnershipService;
use Polymorph\Platform\SharedKernel\Ownership\ResourceType;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use Polymorph\Platform\Support\Validation\Rules\ObjectLikeArray;
use Polymorph\Platform\Support\Validation\ValidationRules as SharedValidationRules;
use Throwable;

/** The single lifecycle owner for schemas and their field trees. */
final class SchemaMutationService
{
    public function __construct(
        private readonly SchemaRepository $schemas,
        private readonly FieldRepository $fields,
        private readonly FieldMutationService $fieldMutations,
        private readonly ResourceOwnershipService $ownership,
        private readonly LockManager $locks,
        private readonly AppLogger $logger,
    ) {}

    /** @param array<string, mixed> $payload */
    public function create(array $payload, ?ResourceOwner $owner = null): SchemaModel
    {
        $validated = $this->validatePayload($payload, creating: true);

        return DB::transaction(function () use ($validated, $owner): SchemaModel {
            $code = (string) $validated['schema']['code'];
            $this->locks->acquireLock(new LockKey('schema-code', $code));

            if ($this->schemas->codeExists($code)) {
                throw DuplicateSchemaCodeException::create($code);
            }

            $schema = $this->schemas->create($validated['schema']);
            $this->seedSystemFields($schema);

            foreach ($validated['upsert'] as $fieldPayload) {
                $this->fieldMutations->create($schema, $fieldPayload);
            }

            $this->ownership->set(ResourceType::SCHEMA, (int) $schema->id, $owner ?? ResourceOwner::platform());
            event(new SchemaChanged((int) $schema->id));

            return $schema->fresh('ownership') ?? $schema;
        });
    }

    /** @param array<string, mixed> $payload */
    public function update(int $schemaId, array $payload): SchemaModel
    {
        $validated = $this->validatePayload($payload, creating: false);

        return DB::transaction(function () use ($schemaId, $validated): SchemaModel {
            $this->locks->acquireLock(new LockKey('schema', $schemaId));
            $schema = $this->schemas->find($schemaId) ?? throw SchemaNotFoundException::byId($schemaId);
            $this->ownership->require(ResourceType::SCHEMA, $schemaId);

            $newCode = $validated['schema']['code'] ?? null;
            if (is_string($newCode) && $newCode !== (string) $schema->code) {
                $this->locks->acquireLock(new LockKey('schema-code', $newCode));
                if ($this->schemas->codeExists($newCode, $schemaId)) {
                    throw DuplicateSchemaCodeException::create($newCode);
                }
            }

            if ($validated['schema'] !== []) {
                $schema = $this->schemas->update($schema, $validated['schema']);
            }
            foreach ($validated['upsert'] as $fieldPayload) {
                array_key_exists('id', $fieldPayload)
                    ? $this->fieldMutations->update($schema, $fieldPayload)
                    : $this->fieldMutations->create($schema, $fieldPayload);
            }
            $this->fieldMutations->deleteMany($schema, $validated['delete']);

            event(new SchemaChanged($schemaId));

            return $schema->fresh('ownership') ?? $schema;
        });
    }

    public function delete(int $schemaId): void
    {
        DB::transaction(function () use ($schemaId): void {
            $this->locks->acquireLock(new LockKey('schema', $schemaId));
            $schema = $this->schemas->find($schemaId) ?? throw SchemaNotFoundException::byId($schemaId);
            $this->ownership->require(ResourceType::SCHEMA, $schemaId);
            $usage = $this->schemas->getUsageInfo($schema);

            if ($usage->isInUse()) {
                throw SchemaInUseException::create($usage);
            }

            $this->ownership->delete(ResourceType::SCHEMA, $schemaId);
            $this->schemas->delete($schema);
            event(new SchemaChanged($schemaId));
        });
    }

    /**
     * Every id is an independent product operation and therefore an independent transaction.
     *
     * @param list<int> $schemaIds
     * @return array{deleted:list<int>,blocked:list<array<string,mixed>>,not_found:list<int>,failed:list<int>}
     */
    public function deleteMany(array $schemaIds): array
    {
        $result = ['deleted' => [], 'blocked' => [], 'not_found' => [], 'failed' => []];

        foreach ($schemaIds as $schemaId) {
            try {
                $this->delete($schemaId);
                $result['deleted'][] = $schemaId;
            } catch (SchemaInUseException $exception) {
                $result['blocked'][] = $exception->usage()->toBlockedEntry();
            } catch (SchemaNotFoundException) {
                $result['not_found'][] = $schemaId;
            } catch (Throwable $exception) {
                $result['failed'][] = $schemaId;
                $this->logger->error('schema.delete_failed', [
                    'schema_id' => $schemaId,
                    'exception' => $exception,
                ]);
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{schema:array<string,mixed>,upsert:list<array<string,mixed>>,delete:list<int>}
     */
    private function validatePayload(array $payload, bool $creating): array
    {
        $payload = $this->normalizeEnums($payload);
        $unknown = array_values(array_diff(array_keys($payload), ['name', 'code', 'description', 'metadata', 'fields']));
        if ($unknown !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'payload' => 'Unknown schema keys: '.implode(', ', $unknown).'.',
            ]);
        }
        $presence = $creating ? ['required'] : ['sometimes', 'required'];

        $validated = Validator::make($payload, [
            'name' => [...$presence, 'string', 'max:255'],
            'code' => SharedValidationRules::slug(required: true, sometimes: ! $creating),
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'fields' => ['sometimes', 'array:upsert,delete'],
            'fields.upsert' => ['sometimes', 'array'],
            'fields.delete' => $creating ? ['prohibited'] : ['sometimes', 'array'],
            'fields.delete.*' => ['integer', 'distinct', 'min:1'],
            'fields.upsert.*' => ['array:id,name,type,cardinality,parent_id,is_indexed,is_system,validation_rules,sort_order,metadata,constraints'],
            'fields.upsert.*.id' => $creating ? ['prohibited'] : ['sometimes', 'integer', 'distinct', 'min:1'],
            'fields.upsert.*.name' => SharedValidationRules::slug(required: false),
            'fields.upsert.*.type' => ['sometimes', Rule::enum(FieldType::class)],
            'fields.upsert.*.cardinality' => ['sometimes', Rule::enum(Cardinality::class)],
            'fields.upsert.*.parent_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'fields.upsert.*.is_indexed' => ['sometimes', 'boolean'],
            'fields.upsert.*.is_system' => ['prohibited'],
            'fields.upsert.*.validation_rules' => ['sometimes', 'nullable', 'array', new ObjectLikeArray(message: 'The :attribute must be an object-like map.')],
            'fields.upsert.*.sort_order' => ['sometimes', 'integer', 'min:0'],
            'fields.upsert.*.metadata' => ['sometimes', 'nullable', 'array'],
            'fields.upsert.*.constraints' => ['sometimes', 'nullable', 'array:allowed_record_definition_id,allowed_mimes'],
            'fields.upsert.*.constraints.allowed_record_definition_id' => ['sometimes', 'nullable', 'integer', 'exists:record_definitions,id'],
            'fields.upsert.*.constraints.allowed_mimes' => ['sometimes', 'array', 'min:1'],
            'fields.upsert.*.constraints.allowed_mimes.*' => ['string', 'distinct', 'max:255'],
        ])->validate();

        $upsert = array_values(is_array($validated['fields']['upsert'] ?? null) ? $validated['fields']['upsert'] : []);
        $delete = array_values(array_map('intval', is_array($validated['fields']['delete'] ?? null) ? $validated['fields']['delete'] : []));

        $errors = [];
        $upsertIds = [];
        foreach ($upsert as $index => $field) {
            $isUpdate = array_key_exists('id', $field);
            if (! $isUpdate && (! isset($field['name'], $field['type'], $field['cardinality']))) {
                $errors["fields.upsert.{$index}"] = 'New fields require name, type and cardinality.';
            }
            if ($isUpdate) {
                $upsertIds[] = (int) $field['id'];
            }
        }
        $overlap = array_values(array_intersect($upsertIds, $delete));
        if ($overlap !== []) {
            $errors['fields'] = 'A field cannot be updated and deleted in the same operation.';
        }
        if ($errors !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        return [
            'schema' => array_intersect_key($validated, array_flip(['name', 'code', 'description', 'metadata'])),
            'upsert' => $upsert,
            'delete' => $delete,
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function normalizeEnums(array $payload): array
    {
        foreach (($payload['fields']['upsert'] ?? []) as $index => $field) {
            if (! is_array($field)) {
                continue;
            }
            foreach (['type', 'cardinality'] as $key) {
                if (($field[$key] ?? null) instanceof \BackedEnum) {
                    $payload['fields']['upsert'][$index][$key] = $field[$key]->value;
                }
            }
        }

        return $payload;
    }

    private function seedSystemFields(SchemaModel $schema): void
    {
        foreach (SystemFieldPolicy::schemaFieldDefinitions() as $definition) {
            $parentId = null;
            $parentPath = $definition['parent_full_path'] ?? null;
            if (is_string($parentPath) && $parentPath !== '') {
                $parentId = $this->fields->findByPath(
                    $schema,
                    \Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldPath::fromString($parentPath),
                )?->id;
            }

            $this->fields->create([
                'schema_id' => (int) $schema->id,
                'parent_id' => $parentId,
                'name' => (string) $definition['name'],
                'full_path' => (string) $definition['full_path'],
                'type' => (string) $definition['type'],
                'cardinality' => (string) ($definition['cardinality'] ?? 'one'),
                'is_indexed' => (bool) ($definition['is_indexed'] ?? true),
                'is_system' => true,
                'validation_rules' => $definition['validation_rules'] ?? null,
                'sort_order' => (int) ($definition['sort_order'] ?? 0),
                'metadata' => $definition['metadata'] ?? null,
            ]);
        }
    }
}
