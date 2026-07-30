<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Services;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\Extensions\Core\Exceptions\ExtensionException as PluginException;
use Polymorph\Platform\Domain\Extensions\Core\ValueObjects\ExtensionCapabilityDefinition;
use Polymorph\Platform\Domain\Extensions\Core\ValueObjects\ExtensionRoleDefinition;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

final class ExtensionAclManifestParser
{
    /**
     * @param  array<string, mixed>  $manifest
     */
    public function validate(array $manifest, string $manifestPath): void
    {
        $pluginId = $manifest['id'] ?? null;
        if (! is_string($pluginId) || trim($pluginId) === '') {
            return;
        }

        $isV2 = is_array(data_get($manifest, 'contributes'));

        try {
            $capabilities = $this->parseCapabilities($manifest, $pluginId);
            foreach ($capabilities as $capability) {
                $resource = trim($capability->resource);
                if ($resource === '') {
                    throw new PluginException('capability resource must be non-empty.');
                }

                if (! $isV2 && ! str_starts_with($resource, "plugin.{$pluginId}.")) {
                    throw new PluginException("capability resource '{$resource}' must start with 'plugin.{$pluginId}.'");
                }

                if (! in_array($capability->action, CapabilityCatalog::policyActions(), true)) {
                    throw new PluginException("unsupported capability action '{$capability->action}'.");
                }

                if (! in_array($capability->scope, ['admin', 'content'], true)) {
                    throw new PluginException("unsupported capability scope '{$capability->scope}'.");
                }
            }

            foreach ($this->parseDefaultAdminRoles($manifest) as $roleCode) {
                if (! preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $roleCode)) {
                    throw new PluginException("invalid role code '{$roleCode}' in acl.defaultAdminRoles.");
                }
            }

            $this->parseRoles($manifest, $pluginId);
        } catch (PluginException $e) {
            throw new PluginException("Manifest {$manifestPath}: {$e->getMessage()}");
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<ExtensionCapabilityDefinition>
     */
    public function parseCapabilities(array $manifest, string $pluginId): array
    {
        $contributes = data_get($manifest, 'contributes');
        if (is_array($contributes)) {
            return $this->parseV2Capabilities($contributes, $pluginId);
        }

        /** @var list<mixed> $entries */
        $entries = array_values((array) data_get($manifest, 'acl.capabilities', []));

        return array_map(
            function (mixed $entry): ExtensionCapabilityDefinition {
                if (is_string($entry)) {
                    $resource = trim($entry);

                    return new ExtensionCapabilityDefinition(
                        resource: $resource,
                        action: CapabilityCatalog::ACTION_ACCESS,
                        label: $this->labelFromResource($resource),
                        scope: 'admin',
                    );
                }

                $entry = is_array($entry) ? $entry : [];
                $resource = trim((string) ($entry['resource'] ?? ''));

                return new ExtensionCapabilityDefinition(
                    resource: $resource,
                    action: strtolower(trim((string) ($entry['action'] ?? CapabilityCatalog::ACTION_ACCESS))),
                    label: trim((string) ($entry['label'] ?? $this->labelFromResource($resource))),
                    scope: strtolower(trim((string) ($entry['scope'] ?? 'admin'))),
                );
            },
            $entries,
        );
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<string>
     */
    public function parseDefaultAdminRoles(array $manifest): array
    {
        $contributes = data_get($manifest, 'contributes');
        if (is_array($contributes)) {
            return $this->parseV2DefaultAdminRoles($contributes);
        }

        $roles = data_get($manifest, 'acl.defaultAdminRoles', [BuiltInRoleCatalog::ROLE_SYSTEM_ADMIN]);
        if (! is_array($roles) || $roles === []) {
            return [BuiltInRoleCatalog::ROLE_SYSTEM_ADMIN];
        }

        /** @var list<string> $normalized */
        $normalized = array_values(array_filter(
            array_map(static fn (mixed $role): string => trim((string) $role), $roles),
            static fn (string $role): bool => $role !== '',
        ));

        return $normalized !== [] ? $normalized : [BuiltInRoleCatalog::ROLE_SYSTEM_ADMIN];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<ExtensionRoleDefinition>
     */
    public function parseRoles(array $manifest, string $pluginId): array
    {
        $contributes = data_get($manifest, 'contributes');
        if (is_array($contributes)) {
            return $this->parseV2Roles($contributes, $pluginId);
        }

        /** @var list<mixed> $entries */
        $entries = array_values((array) data_get($manifest, 'acl.roles', []));

        return array_map(
            function (mixed $entry) use ($pluginId): ExtensionRoleDefinition {
                /** @var array<string, mixed> $entry */
                $entry = is_array($entry) ? $entry : [];
                $code = trim((string) ($entry['code'] ?? ''));
                $this->assertRoleCodeNotBuiltIn($code);

                // Тот же префикс, что validate() требует от acl.capabilities.
                // Раньше capability РОЛЕЙ не проверялись вовсе: V1-манифест с
                // acl.roles[].capabilities=['user.lifecycle'] при установке
                // создавал allow-политику на чужой ресурс ядра и вешал её на
                // роль — эскалация привилегий плагином.
                $capabilities = array_values(array_filter(array_map(
                    fn (mixed $c): string => $this->assertPluginResource(trim((string) $c), $pluginId),
                    (array) ($entry['capabilities'] ?? []),
                ), static fn (string $c): bool => $c !== ''));

                return new ExtensionRoleDefinition(
                    code: $code,
                    name: trim((string) ($entry['name'] ?? '')),
                    description: trim((string) ($entry['description'] ?? '')),
                    capabilities: $capabilities,
                );
            },
            $entries,
        );
    }

    /**
     * @param  array<string, mixed>  $contributes
     * @return list<ExtensionCapabilityDefinition>
     */
    public function parseV2Capabilities(array $contributes, string $pluginId): array
    {
        /** @var list<mixed> $entries */
        $entries = array_values((array) data_get($contributes, 'capabilities', []));

        return array_map(
            fn (mixed $entry): ExtensionCapabilityDefinition => $this->parseV2CapabilityEntry($entry, $pluginId),
            $entries,
        );
    }

    /**
     * @param  array<string, mixed>  $contributes
     * @return list<string>
     */
    public function parseV2DefaultAdminRoles(array $contributes): array
    {
        $roles = data_get($contributes, 'defaultAdminRoles', [BuiltInRoleCatalog::ROLE_SYSTEM_ADMIN]);
        if (! is_array($roles) || $roles === []) {
            return [BuiltInRoleCatalog::ROLE_SYSTEM_ADMIN];
        }

        /** @var list<string> $normalized */
        $normalized = array_values(array_filter(
            array_map(static fn (mixed $role): string => trim((string) $role), $roles),
            static fn (string $role): bool => $role !== '',
        ));

        return $normalized !== [] ? $normalized : [BuiltInRoleCatalog::ROLE_SYSTEM_ADMIN];
    }

    /**
     * @param  array<string, mixed>  $contributes
     * @return list<ExtensionRoleDefinition>
     */
    public function parseV2Roles(array $contributes, string $pluginId): array
    {
        /** @var list<mixed> $entries */
        $entries = array_values((array) data_get($contributes, 'roles', []));

        return array_map(
            function (mixed $entry) use ($pluginId): ExtensionRoleDefinition {
                /** @var array<string, mixed> $entry */
                $entry = is_array($entry) ? $entry : [];
                $code = trim((string) ($entry['code'] ?? ''));
                $this->assertRoleCodeNotBuiltIn($code);
                $capabilities = array_values(array_filter(array_map(
                    fn (mixed $c): string => $this->assertExtResource(trim((string) $c), $pluginId),
                    (array) ($entry['capabilities'] ?? []),
                ), static fn (string $c): bool => $c !== ''));

                return new ExtensionRoleDefinition(
                    code: $code,
                    name: trim((string) ($entry['name'] ?? $this->labelFromResource($code))),
                    description: trim((string) ($entry['description'] ?? '')),
                    capabilities: $capabilities,
                );
            },
            $entries,
        );
    }

    private function parseV2CapabilityEntry(mixed $entry, string $pluginId): ExtensionCapabilityDefinition
    {
        if (is_string($entry)) {
            $resource = $this->assertExtResource(trim($entry), $pluginId);

            return new ExtensionCapabilityDefinition(
                resource: $resource,
                action: CapabilityCatalog::ACTION_ACCESS,
                label: $this->labelFromResource($resource),
                scope: 'admin',
            );
        }

        if (! is_array($entry)) {
            throw new PluginException('v2 capability must be a string or object, got '.gettype($entry).'.');
        }

        $resource = $this->assertExtResource(trim((string) ($entry['resource'] ?? '')), $pluginId);
        $action = strtolower(trim((string) ($entry['action'] ?? CapabilityCatalog::ACTION_ACCESS)));

        return new ExtensionCapabilityDefinition(
            resource: $resource,
            action: $action,
            label: trim((string) ($entry['label'] ?? $this->labelFromResource($resource))),
            scope: strtolower(trim((string) ($entry['scope'] ?? 'admin'))),
        );
    }

    private function assertExtResource(string $resource, string $pluginId): string
    {
        if ($resource === '' || ! str_starts_with($resource, "ext.{$pluginId}.")) {
            throw new PluginException("v2 capability resource '{$resource}' must start with 'ext.{$pluginId}.'.");
        }

        return $resource;
    }

    private function assertPluginResource(string $resource, string $pluginId): string
    {
        if ($resource === '' || ! str_starts_with($resource, "plugin.{$pluginId}.")) {
            throw new PluginException("role capability '{$resource}' must start with 'plugin.{$pluginId}.'.");
        }

        return $resource;
    }

    /**
     * Роль плагина не может носить код встроенной роли: ensurePluginRoles делает
     * updateOrCreate по коду, и плагин мог бы «переименовать» system.admin или
     * навесить свои политики на любую built-in роль.
     */
    private function assertRoleCodeNotBuiltIn(string $code): void
    {
        if (BuiltInRoleCatalog::isProtected($code)) {
            throw new PluginException("role code '{$code}' collides with a built-in role.");
        }
    }

    private function labelFromResource(string $resource): string
    {
        $suffix = substr($resource, (int) strrpos($resource, '.') + 1);

        return ucfirst(str_replace(['_', '-'], ' ', $suffix));
    }
}
