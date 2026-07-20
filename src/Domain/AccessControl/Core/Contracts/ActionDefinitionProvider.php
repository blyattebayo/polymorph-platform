<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\Contracts;

interface ActionDefinitionProvider
{
    /**
     * Additional policy actions contributed by a domain or plugin module.
     *
     * @return list<string>
     */
    public function actions(): array;
}
