<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Core\Contracts;

interface UiConfigDocument
{
    public function rawDocument(): string;
}
