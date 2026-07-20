<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Rules;

use Polymorph\Platform\Domain\Records\Support\RecordSchemaScalarValueNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * Принимает только ISO-8601 datetime с обязательным timezone (нормализуется в UTC на write).
 */
final class Iso8601DateTime implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            $fail('The :attribute must be a valid ISO-8601 datetime with timezone.');

            return;
        }

        $stringValue = (string) $value;
        if (preg_match(RecordSchemaScalarValueNormalizer::DATETIME_PATTERN, $stringValue) !== 1) {
            $fail('The :attribute must be a valid ISO-8601 datetime with timezone.');

            return;
        }

        try {
            Carbon::parse($stringValue);
        } catch (\Throwable) {
            $fail('The :attribute must be a valid ISO-8601 datetime with timezone.');
        }
    }
}
