<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Listeners;

use Polymorph\Platform\Domain\SchemaModel\Events\Contracts\SchemaChangeEvent;
use Polymorph\Platform\Domain\SchemaModelValidation\Cache\RuleSetCache;

/**
 * Сбрасывает кэш скомпилированных правил валидации при изменении схемы/полей.
 *
 * Перенесено из FieldObserver/SchemaObserver (SchemaModel больше не лезет в
 * домен валидации напрямую) — домен валидации сам подписан на SchemaChangeEvent.
 */
final class ForgetRuleSetCacheOnSchemaChange
{
    public function __construct(
        private readonly RuleSetCache $ruleSetCache,
    ) {}

    public function handle(SchemaChangeEvent $event): void
    {
        $this->ruleSetCache->forget($event->schemaId());
    }
}
