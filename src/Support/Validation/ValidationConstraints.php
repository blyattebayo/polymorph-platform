<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Validation;

use Polymorph\Platform\Support\Validation\Constraints\EmailConstraint;
use Polymorph\Platform\Support\Validation\Constraints\PasswordConstraint;
use Polymorph\Platform\Support\Validation\Constraints\PatternConstraint;

/**
 * ЯДРОВЫЙ аксессор правил валидации — источник правды. Владеет правилами
 * (config/validation_constraints.php) и отдаёт типизированные ядровые DTO
 * (Polymorph\Platform\Support\Validation\Constraints\*). Используется кодом ядра напрямую.
 *
 * Плагинам та же поверхность доступна через нейтральный V2-контракт
 * (Polymorph\Sdk\Validation\ValidationConstraints); host-адаптер
 * Polymorph\Platform\Domain\Extensions\SdkBridge\SdkValidationConstraints маппит эти ядровые VO
 * в SDK VO — плагинам нельзя трогать Polymorph\Platform\*.
 */
final class ValidationConstraints
{
    public static function password(): PasswordConstraint
    {
        return new PasswordConstraint(
            min: self::intValue('password.min'),
            max: self::intValue('password.max'),
        );
    }

    public static function email(): EmailConstraint
    {
        return new EmailConstraint(
            max: self::intValue('email.max'),
            laravelRule: self::stringValue('email.laravel'),
            normalizationRule: self::stringValue('email.normalize'),
        );
    }

    public static function slug(): PatternConstraint
    {
        return new PatternConstraint(
            pattern: self::stringValue('slug.pattern'),
            max: self::intValue('slug.max'),
        );
    }

    public static function aclAction(): PatternConstraint
    {
        return new PatternConstraint(
            pattern: self::stringValue('aclAction.pattern'),
            max: self::intValue('aclAction.max'),
        );
    }

    public static function roleCode(): PatternConstraint
    {
        return new PatternConstraint(
            pattern: self::stringValue('roleCode.pattern'),
            max: self::intValue('roleCode.max'),
        );
    }

    /**
     * Клиентский манифест (для отдачи фронту через ValidationRulesController) —
     * без BE-adapter полей (email.laravel/normalize).
     *
     * @return array<string, mixed>
     */
    public static function clientManifest(): array
    {
        return [
            'password' => ['min' => self::password()->min(), 'max' => self::password()->max()],
            'email' => ['max' => self::email()->max()],
            'slug' => ['max' => self::slug()->max(), 'pattern' => self::slug()->pattern()],
            'roleCode' => ['max' => self::roleCode()->max(), 'pattern' => self::roleCode()->pattern()],
            'aclAction' => ['max' => self::aclAction()->max(), 'pattern' => self::aclAction()->pattern()],
        ];
    }

    /** @return array<string, mixed> */
    public static function manifest(): array
    {
        $configured = config('validation_constraints');
        if (! is_array($configured) || $configured === []) {
            throw new \RuntimeException('Missing or invalid config/validation_constraints.php — core must define validation rules.');
        }

        return $configured;
    }

    private static function stringValue(string $path): string
    {
        $value = self::value($path);
        if (! is_string($value) || $value === '') {
            throw new \RuntimeException("Validation constraint value '{$path}' must be a non-empty string.");
        }

        return $value;
    }

    private static function intValue(string $path): int
    {
        $value = self::value($path);
        if (! is_int($value)) {
            throw new \RuntimeException("Validation constraint value '{$path}' must be an integer.");
        }

        return $value;
    }

    private static function value(string $path): mixed
    {
        $value = self::manifest();
        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                throw new \RuntimeException("Validation constraint value '{$path}' is missing.");
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
