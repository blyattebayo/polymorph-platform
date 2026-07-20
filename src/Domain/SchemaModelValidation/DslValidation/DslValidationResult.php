<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\DslValidation;

final class DslValidationResult
{
    /**
     * @var array<string, list<string>>
     */
    private array $errors = [];

    public function addError(string $path, string $message): void
    {
        $key = trim($path) !== '' ? $path : 'validation_rules';
        $this->errors[$key] ??= [];
        $this->errors[$key][] = $message;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            if ($messages !== []) {
                return $messages[0];
            }
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function toArray(): array
    {
        return $this->errors;
    }
}
