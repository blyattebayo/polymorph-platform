<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class UniqueByKeys implements ValidationRule
{
    /**
     * @param  list<string>  $keys
     */
    public function __construct(
        private readonly array $keys,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || $value === []) {
            return;
        }

        if ($this->keys === []) {
            return;
        }

        $seen = [];

        foreach ($value as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $tuple = [];
            foreach ($this->keys as $key) {
                $tuple[] = data_get($item, $this->normalizeKey($key));
            }

            $signature = json_encode($tuple, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($signature === false) {
                continue;
            }

            if (isset($seen[$signature])) {
                $fail("The :attribute field has duplicate element by keys at index {$index}.");

                return;
            }

            $seen[$signature] = true;
        }
    }

    private function normalizeKey(string $key): string
    {
        if (str_starts_with($key, '$self.')) {
            return ltrim(substr($key, strlen('$self.')), '.');
        }

        return $key;
    }
}
