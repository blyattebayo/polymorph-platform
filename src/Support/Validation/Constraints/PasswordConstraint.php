<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Validation\Constraints;

/**
 * Ядровый VO правила пароля. Перенесён из Polymorph\PluginSdk\Validation при сносе V1 SDK.
 */
final readonly class PasswordConstraint
{
    public function __construct(
        private int $min,
        private int $max,
    ) {}

    public function min(): int
    {
        return $this->min;
    }

    public function max(): int
    {
        return $this->max;
    }
}
