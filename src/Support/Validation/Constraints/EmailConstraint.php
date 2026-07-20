<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Validation\Constraints;

/**
 * Ядровый VO правила email. Перенесён из Polymorph\PluginSdk\Validation при сносе V1 SDK.
 */
final readonly class EmailConstraint
{
    public function __construct(
        private int $max,
        private string $laravelRule,
        private string $normalizationRule,
    ) {}

    public function max(): int
    {
        return $this->max;
    }

    public function laravelRule(): string
    {
        return $this->laravelRule;
    }

    public function normalizationRule(): string
    {
        return $this->normalizationRule;
    }

    public function normalize(string $email): string
    {
        $normalized = trim($email);

        if ($this->normalizationRule === 'lowercase') {
            return strtolower($normalized);
        }

        throw new \RuntimeException("Unsupported email normalization rule '{$this->normalizationRule}'.");
    }

    public function isValid(string $email): bool
    {
        return mb_strlen($email) <= $this->max
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
