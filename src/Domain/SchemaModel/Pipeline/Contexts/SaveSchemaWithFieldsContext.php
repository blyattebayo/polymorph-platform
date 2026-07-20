<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Pipeline\Contexts;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Data\CreateFieldData;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Data\UpdateFieldData;
use Polymorph\Platform\PipelineCore\Locking\LockKey;
use Polymorph\Platform\PipelineCore\Runtime\LockableContext;

final class SaveSchemaWithFieldsContext implements LockableContext
{
    public ?SchemaModel $savedSchema = null;

    /**
     * @var array<int, array{field: Field, constraints: array<string, mixed>|null}>
     */
    public array $fieldsForConstraintSync = [];

    /**
     * @var array<int, array{data: CreateFieldData|UpdateFieldData, field: Field|null}>
     */
    public array $parsedFieldsUpsert = [];

    public function __construct(
        public readonly array $schemaPayload,
        public readonly array $fieldsUpsert,
        public readonly array $fieldsDelete,
        public readonly ?SchemaModel $existingSchema,
        public readonly string $operationId,
    ) {}

    public function schema(): ?SchemaModel
    {
        return $this->savedSchema ?? $this->existingSchema;
    }

    public function getLockKey(): LockKey
    {
        $schema = $this->schema();
        if ($schema instanceof SchemaModel && (int) $schema->id > 0) {
            return new LockKey(
                resourceType: 'schema',
                resourceId: (int) $schema->id,
            );
        }

        $schemaCode = trim((string) ($this->schemaPayload['code'] ?? ''));
        $resourceId = $schemaCode !== ''
            ? 'create:'.$schemaCode
            : 'create:'.$this->operationId;

        return new LockKey(
            resourceType: 'schema',
            resourceId: $resourceId,
        );
    }
}
