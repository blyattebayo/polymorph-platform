<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Core\ValueObjects;

/**
 * Роль, объявленная расширением в манифесте (acl.roles[]).
 */
final readonly class ExtensionRoleDefinition
{
    /**
     * @param list<string> $capabilities resource-паттерны прав расширения (ext.{id}.*)
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $description,
        public array $capabilities,
    ) {
    }
}
