<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\Contracts;

interface ActionRegistry
{
    public function allows(string $action): bool;

    /**
     * @return list<string>
     */
    public function actions(): array;
}
