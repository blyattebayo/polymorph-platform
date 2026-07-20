<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\Contracts;

interface AuditActorResolver
{
    public function resolve(): string;
}
