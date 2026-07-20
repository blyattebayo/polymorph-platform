<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ObjectLikeArray implements ValidationRule
{
    public function __construct(
        private readonly bool $allowEmptyList = false,
        private readonly string $message = 'The :attribute field must be an object-like payload.',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        if ($this->allowEmptyList && $value === []) {
            return;
        }

        if (! array_is_list($value)) {
            return;
        }

        $fail(str_replace(':attribute', $attribute, $this->message));
    }
}
