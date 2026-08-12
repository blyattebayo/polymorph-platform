<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Listeners;

use Polymorph\Platform\Domain\SchemaModel\Events\SchemaChanged;
use Polymorph\Platform\Domain\SchemaModelValidation\Cache\RuleSetCache;

/**
 * Сбрасывает кэш скомпилированных правил валидации при изменении схемы/полей.
 *
 * Validation owns invalidation of its derived rule cache.
 */
final class ForgetRuleSetCacheOnSchemaChange
{
    public function __construct(
        private readonly RuleSetCache $ruleSetCache,
    ) {}

    public function handle(SchemaChanged $event): void
    {
        $this->ruleSetCache->forget($event->schemaId);
    }
}
