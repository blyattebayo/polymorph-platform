<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Validation\Constraints;

/**
 * Ядровый VO правила-паттерна. Раньше жил в Polymorph\PluginSdk\Validation —
 * перенесён в ядро при сносе V1 SDK (ядро владеет правилами валидации как
 * источник правды; V2-мост маппит эти VO в нейтральные Polymorph\Sdk\Validation\*).
 */
final readonly class PatternConstraint
{
    public function __construct(
        private string $pattern,
        private ?int $max = null,
    ) {}

    public function pattern(): string
    {
        return $this->pattern;
    }

    public function phpPattern(): string
    {
        return '/'.str_replace('/', '\\/', $this->pattern).'/';
    }

    public function max(): ?int
    {
        return $this->max;
    }

    public function matches(string $value): bool
    {
        return preg_match($this->phpPattern(), $value) === 1
            && ($this->max === null || mb_strlen($value) <= $this->max);
    }

    /**
     * @return list<string>
     */
    public function laravelRules(bool $required = true, bool $sometimes = false, bool $nullable = false, ?int $max = null): array
    {
        $resolvedMax = $max ?? $this->max;

        return [
            ...self::presence($required, $sometimes, $nullable),
            'string',
            ...($resolvedMax !== null ? ['max:'.$resolvedMax] : []),
            'regex:'.$this->phpPattern(),
        ];
    }

    /**
     * @return list<string>
     */
    private static function presence(bool $required, bool $sometimes, bool $nullable): array
    {
        if ($sometimes) {
            return $required ? ['sometimes', 'required'] : ['sometimes'];
        }

        if ($required) {
            return ['required'];
        }

        return $nullable ? ['nullable'] : ['sometimes'];
    }
}
