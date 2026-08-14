<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\SdkBridge;

use Polymorph\Sdk\Data\DefinitionRef;
use Polymorph\Sdk\Data\DefinitionRegistry;
use Polymorph\Sdk\Data\SchemaSpec;
use Polymorph\Sdk\Extension\ExtensionContext;

/** Binds the SDK definition registry to an explicit extension context. */
final class SdkDefinitionRegistry implements DefinitionRegistry
{
    public function __construct(
        private readonly ExtensionDataGateway $platform,
        private readonly ExtensionContext $context,
    ) {}

    public function ensure(string $entity, SchemaSpec $spec): DefinitionRef
    {
        return $this->platform->ensureDefinition($this->context->id->value, $entity, $spec);
    }
}
