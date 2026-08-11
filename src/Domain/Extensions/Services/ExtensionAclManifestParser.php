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
     * @param  array<string, mixed>  $contributes
     * @return list<ExtensionCapabilityDefinition>
     */
    public function parseCapabilities(array $contributes, string $extensionId): array
    {
        $entries = array_values((array) ($contributes['capabilities'] ?? []));

        return array_map(
            fn (mixed $entry): ExtensionCapabilityDefinition => $this->parseCapability($entry, $extensionId),
            $entries,
        );
    }

    /**
     * @param  array<string, mixed>  $contributes
     * @return list<string>
     */
    public function parseDefaultAdminRoles(array $contributes): array
    {
        $entries = $contributes['defaultAdminRoles'] ?? [BuiltInRoleCatalog::ROLE_SYSTEM_ADMIN];
        if (! is_array($entries) || $entries === []) {
            return [BuiltInRoleCatalog::ROLE_SYSTEM_ADMIN];
        }

        $roles = [];
        foreach ($entries as $entry) {
            $code = is_string($entry) ? trim($entry) : '';
            $this->assertRoleCode($code);
            $roles[] = $code;
        }

        return array_values(array_unique($roles));
    }

    /**
     * @param  array<string, mixed>  $contributes
     * @return list<ExtensionRoleDefinition>
     */
    public function parseRoles(array $contributes, string $extensionId): array
    {
        $entries = array_values((array) ($contributes['roles'] ?? []));

        return array_map(function (mixed $entry) use ($extensionId): ExtensionRoleDefinition {
            if (! is_array($entry)) {
                throw new PluginException('Extension role must be an object.');
            }

            $code = trim((string) ($entry['code'] ?? ''));
            $this->assertRoleCode($code);
            if (BuiltInRoleCatalog::isProtected($code)) {
                throw new PluginException("Role code '{$code}' collides with a built-in role.");
            }

            $capabilities = [];
            foreach ((array) ($entry['capabilities'] ?? []) as $resource) {
                if (! is_string($resource)) {
                    throw new PluginException("Role '{$code}' capability must be a string.");
                }
                $capabilities[] = $this->assertExtensionResource(trim($resource), $extensionId);
            }

            return new ExtensionRoleDefinition(
                code: $code,
                name: trim((string) ($entry['name'] ?? $this->labelFromResource($code))),
                description: trim((string) ($entry['description'] ?? '')),
                capabilities: array_values(array_unique($capabilities)),
            );
        }, $entries);
    }

    private function parseCapability(mixed $entry, string $extensionId): ExtensionCapabilityDefinition
    {
        if (is_string($entry)) {
            $resource = $this->assertExtensionResource(trim($entry), $extensionId);

            return new ExtensionCapabilityDefinition(
                resource: $resource,
                action: CapabilityCatalog::ACTION_ACCESS,
                label: $this->labelFromResource($resource),
                scope: 'admin',
            );
        }
        if (! is_array($entry)) {
            throw new PluginException('Extension capability must be a string or object.');
        }

        $resource = $this->assertExtensionResource(trim((string) ($entry['resource'] ?? '')), $extensionId);
        $action = strtolower(trim((string) ($entry['action'] ?? CapabilityCatalog::ACTION_ACCESS)));
        if (! in_array($action, CapabilityCatalog::policyActions(), true)) {
            throw new PluginException("Unsupported capability action '{$action}'.");
        }
        $scope = strtolower(trim((string) ($entry['scope'] ?? 'admin')));
        if (! in_array($scope, ['admin', 'content'], true)) {
            throw new PluginException("Unsupported capability scope '{$scope}'.");
        }

        return new ExtensionCapabilityDefinition(
            resource: $resource,
            action: $action,
            label: trim((string) ($entry['label'] ?? $this->labelFromResource($resource))),
            scope: $scope,
        );
    }

    private function assertExtensionResource(string $resource, string $extensionId): string
    {
        if ($resource === '' || ! str_starts_with($resource, "ext.{$extensionId}.")) {
            throw new PluginException(
                "Capability resource '{$resource}' must start with 'ext.{$extensionId}.'.",
            );
        }

        return $resource;
    }

    private function assertRoleCode(string $code): void
    {
        if (preg_match('/^[a-z][a-z0-9_.-]*$/', $code) !== 1) {
            throw new PluginException("Invalid role code '{$code}'.");
        }
    }

    private function labelFromResource(string $resource): string
    {
        $suffix = substr($resource, (int) strrpos($resource, '.') + 1);

        return ucfirst(str_replace(['_', '-'], ' ', $suffix));
    }
}
