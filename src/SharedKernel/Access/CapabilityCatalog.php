<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Access;

final class CapabilityCatalog
{
    public const ACTION_ACCESS = 'access';

    public const ACTION_READ = 'read';

    public const ACTION_WRITE = 'write';

    public const ACTION_DELETE = 'delete';

    public const ACTION_MANAGE = 'manage';

    public const ACTION_WILDCARD = '*';

    public const EFFECT_ALLOW = 'allow';

    /**
     * Core platform policy actions. Domains may add more via ActionDefinitionProvider
     * (tag: access.action_providers).
     *
     * @return list<string>
     */
    public static function policyActions(): array
    {
        return [
            self::ACTION_ACCESS,
            self::ACTION_READ,
            self::ACTION_WRITE,
            self::ACTION_DELETE,
            self::ACTION_MANAGE,
            self::ACTION_WILDCARD,
        ];
    }

    public static function capabilityKey(string $resource, string $action = self::ACTION_ACCESS): string
    {
        return $resource.'/'.$action;
    }

    /**
     * Ключи нескольких действий одного ресурса — для defaultRoleAssignments
     * манифестов: keys('media', 'read', 'write') вместо трёх capabilityKey().
     *
     * @return list<string>
     */
    public static function keys(string $resource, string ...$actions): array
    {
        return array_map(
            static fn (string $action): string => self::capabilityKey($resource, $action),
            array_values($actions),
        );
    }

    /**
     * Alias middleware-требования маршрута. Формат — здесь, а не в middleware:
     * его строят манифесты доменов (requireRead() и т.п.), и зависимость
     * Domain -> Http\Middleware была бы направлена не в ту сторону.
     */
    public const MIDDLEWARE_ALIAS = 'capability.require';

    public static function requirement(string $resource, string $action = self::ACTION_ACCESS): string
    {
        $resource = trim($resource);
        $action = trim($action);

        if ($resource === '' || $action === '') {
            throw new \InvalidArgumentException('Capability resource and action must not be empty.');
        }

        return self::MIDDLEWARE_ALIAS.':'.$resource.','.$action;
    }
}
