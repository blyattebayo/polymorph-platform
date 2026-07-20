<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Validation;

use Polymorph\Platform\Support\Validation\ValidationConstraints;

final class ValidationRules
{
    /**
     * @return list<string>
     */
    public static function email(bool $required = true, bool $sometimes = false, bool $nullable = false): array
    {
        $constraint = ValidationConstraints::email();

        return [
            ...self::presence($required, $sometimes, $nullable),
            'string',
            $constraint->laravelRule(),
            $constraint->normalizationRule(),
            'max:' . $constraint->max(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function password(bool $required = true, bool $sometimes = false, bool $nullable = false, bool $confirmed = false): array
    {
        $constraint = ValidationConstraints::password();
        $rules = [
            ...self::presence($required, $sometimes, $nullable),
            'string',
            'min:' . $constraint->min(),
            'max:' . $constraint->max(),
        ];

        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }

    /**
     * @return list<string>
     */
    public static function slug(bool $required = true, bool $sometimes = false, bool $nullable = false, ?int $max = null): array
    {
        $constraint = ValidationConstraints::slug();

        return [
            ...self::presence($required, $sometimes, $nullable),
            'string',
            'max:' . ($max ?? $constraint->max()),
            'regex:' . $constraint->phpPattern(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function aclAction(bool $required = true, bool $sometimes = false, bool $nullable = false): array
    {
        $constraint = ValidationConstraints::aclAction();

        return [
            ...self::presence($required, $sometimes, $nullable),
            'string',
            'max:' . $constraint->max(),
            'regex:' . $constraint->phpPattern(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function roleCode(bool $required = true, bool $sometimes = false, bool $nullable = false, ?int $max = null): array
    {
        $constraint = ValidationConstraints::roleCode();

        return [
            ...self::presence($required, $sometimes, $nullable),
            'string',
            'max:' . ($max ?? $constraint->max()),
            'regex:' . $constraint->phpPattern(),
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
