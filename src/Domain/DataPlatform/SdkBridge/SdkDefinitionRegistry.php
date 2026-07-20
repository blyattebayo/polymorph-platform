<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\SdkBridge;

use Polymorph\Platform\Domain\DataPlatform\DataPlatform;
use Polymorph\Sdk\Data\DefinitionRef;
use Polymorph\Sdk\Data\DefinitionRegistry;
use Polymorph\Sdk\Data\SchemaSpec;
use Polymorph\Sdk\Extension\ExtensionContext;

/**
 * Host-адаптер SDK-контракта {@see DefinitionRegistry}, СКОУПНУТЫЙ на расширение
 * через {@see ExtensionContext} (скоуп ЯВНЫЙ — без ambient-магии V1). Делегирует
 * единственному шву {@see DataPlatform}, подставляя id расширения.
 */
final class SdkDefinitionRegistry implements DefinitionRegistry
{
    public function __construct(
        private readonly DataPlatform $platform,
        private readonly ExtensionContext $context,
    ) {
    }

    public function ensure(string $entity, SchemaSpec $spec): DefinitionRef
    {
        return $this->platform->ensureDefinition($this->context->id->value, $entity, $spec);
    }
}
