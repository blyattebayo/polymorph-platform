<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Core\Contracts;

interface UiConfigDocument
{
    public function rawDocument(): string;

    public function value(): mixed;

    public function version(): int;

    public function revision(): int;
}
