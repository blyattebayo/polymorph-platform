<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

final class CollectionExistsIn implements ValidationRule, DataAwareRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function __construct(
        private readonly string $sourceOperand,
        private readonly string $foreignCollectionPath,
        private readonly string $foreignField,
        private readonly string $mode = 'all',
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

        $foreign = data_get($this->data, $this->foreignCollectionPath, []);
        if (! is_array($foreign) || $foreign === []) {
            $fail('The :attribute field has unresolved foreign collection.');
            return;
        }

        $lookup = [];
        foreach ($foreign as $item) {
            if (! is_array($item)) {
                continue;
            }

            $lookup[] = data_get($item, $this->foreignField);
        }

        $matches = 0;
        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }

            $source = $this->resolveSource($item);
            if (in_array($source, $lookup, true)) {
                $matches++;
            }
        }

        $total = count($value);
        $ok = match ($this->mode) {
            'all' => $total > 0 && $matches === $total,
            'any' => $matches > 0,
            default => true,
        };

        if (! $ok) {
            $fail('The :attribute field contains values outside the allowed collection.');
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function resolveSource(array $item): mixed
    {
        if (str_starts_with($this->sourceOperand, '$self.')) {
            $path = ltrim(substr($this->sourceOperand, strlen('$self.')), '.');

            return data_get($item, $path);
        }

        return data_get($item, $this->sourceOperand);
    }
}
