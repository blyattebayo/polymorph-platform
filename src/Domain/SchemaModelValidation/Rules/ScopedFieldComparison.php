<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Rules;

use Polymorph\Platform\Domain\SchemaModelValidation\Rules\Support\ScopedPathResolver;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

final class ScopedFieldComparison implements ValidationRule, DataAwareRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function __construct(
        private readonly string $operator,
        private readonly string $otherFieldTemplate,
        private readonly mixed $constantValue = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        $compareValue = $this->constantValue;

        if ($compareValue === null) {
            if ($this->otherFieldTemplate === '') {
                return;
            }

            $resolvedPath = ScopedPathResolver::resolve($attribute, $this->otherFieldTemplate);
            $compareValue = data_get($this->data, $resolvedPath);
        }

        if ($compareValue === null && $this->constantValue === null) {
            return;
        }

        $result = match ($this->operator) {
            '>=' => $this->compare($value, $compareValue) >= 0,
            '<=' => $this->compare($value, $compareValue) <= 0,
            '>' => $this->compare($value, $compareValue) > 0,
            '<' => $this->compare($value, $compareValue) < 0,
            '==' => $this->compare($value, $compareValue) === 0,
            '!=' => $this->compare($value, $compareValue) !== 0,
            default => false,
        };

        if (! $result) {
            $fail("The :attribute must be {$this->operator} {$this->otherFieldTemplate}.");
        }
    }

    private function compare(mixed $left, mixed $right): int
    {
        if ($left instanceof \DateTimeInterface && $right instanceof \DateTimeInterface) {
            return $left <=> $right;
        }

        if (is_string($left) && is_string($right)) {
            try {
                $dateLeft = Carbon::parse($left);
                $dateRight = Carbon::parse($right);

                return $dateLeft <=> $dateRight;
            } catch (\Throwable) {
            }
        }

        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left <=> (float) $right;
        }

        return $left <=> $right;
    }
}
