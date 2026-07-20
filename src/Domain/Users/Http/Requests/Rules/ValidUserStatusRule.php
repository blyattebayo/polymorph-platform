<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Http\Requests\Rules;

use Polymorph\Platform\Domain\Users\Core\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidUserStatusRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! in_array((string) $value, User::allowedStatuses(), true)) {
            $fail('The selected status is invalid.');
        }
    }
}
