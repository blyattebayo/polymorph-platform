<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Rules;

use Polymorph\Platform\Domain\SchemaModelValidation\Rules\Support\ScopedPathResolver;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

final class CollectionQuantifier implements ValidationRule, DataAwareRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function __construct(
        private readonly string $quantifier,
        private readonly string $leftOperand,
        private readonly string $operator,
        private readonly ?string $rightOperandTemplate = null,
        private readonly mixed $rightConstantValue = null,
        private readonly int $countGte = 1,
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
        if (! is_array($value)) {
            return;
        }

        $matches = 0;
        $total = count($value);

        foreach ($value as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $left = $this->resolveOperand($attribute, (string) $index, $item, $this->leftOperand);
            $right = $this->rightOperandTemplate !== null
                ? $this->resolveOperand($attribute, (string) $index, $item, $this->rightOperandTemplate)
                : $this->rightConstantValue;

            if ($this->evaluate($left, $right)) {
                $matches++;
            }
        }

        $ok = match ($this->quantifier) {
            'all' => $total > 0 && $matches === $total,
            'any' => $matches > 0,
            'count' => $matches >= $this->countGte,
            default => true,
        };

        if (! $ok) {
            $fail("The :attribute field failed quantifier {$this->quantifier} validation.");
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function resolveOperand(string $attribute, string $index, array $item, string $operand): mixed
    {
        if (str_starts_with($operand, '$self.')) {
            return data_get($item, ltrim(substr($operand, strlen('$self.')), '.'));
        }

        $resolvedPath = ScopedPathResolver::resolve($attribute . '.' . $index, $operand);

        return data_get($this->data, $resolvedPath);
    }

    private function evaluate(mixed $left, mixed $right): bool
    {
        return match ($this->operator) {
            '==' => $left == $right,
            '!=' => $left != $right,
            '>' => $left > $right,
            '<' => $left < $right,
            '>=' => $left >= $right,
            '<=' => $left <= $right,
            default => false,
        };
    }
}
