<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Rules;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ImplicitRule;
use Polymorph\Platform\Domain\SchemaModelValidation\Dsl\DslOperator;
use Polymorph\Platform\Domain\SchemaModelValidation\Rules\Support\ScopedPathResolver;

final class ScopedRequiredIf implements DataAwareRule, ImplicitRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    private string $message = 'The :attribute field is required when condition is met.';

    public function __construct(
        private readonly string $otherFieldTemplate,
        private readonly mixed $expectedValue,
        private readonly string $operator = '==',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }

    public function passes($attribute, $value): bool
    {
        if ($this->otherFieldTemplate === '') {
            return true;
        }

        $resolvedPath = ScopedPathResolver::resolve((string) $attribute, $this->otherFieldTemplate);
        $actualValue = data_get($this->data, $resolvedPath);

        if (! $this->evaluateCondition($actualValue, $this->expectedValue)) {
            return true;
        }

        return ! $this->isEmpty($value);
    }

    public function message(): string
    {
        return $this->message;
    }

    private function evaluateCondition(mixed $left, mixed $right): bool
    {
        return DslOperator::tryFrom($this->operator)?->evaluate($left, $right) ?? false;
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }
}
