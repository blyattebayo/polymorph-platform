<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Pipeline\Contexts;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\PipelineCore\Locking\LockKey;
use Polymorph\Platform\PipelineCore\Runtime\LockableContext;

final class DeleteSchemaContext implements LockableContext
{
    public function __construct(
        public readonly SchemaModel $schema,
    ) {}

    public function getLockKey(): LockKey
    {
        return new LockKey(
            resourceType: 'schema',
            resourceId: (int) $this->schema->id,
        );
    }
}
