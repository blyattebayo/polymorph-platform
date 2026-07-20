<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Core\ValueObjects;

final readonly class DiscoveredExtension
{
    /**
     * @param  list<ExtensionCapabilityDefinition>  $capabilityDefinitions
     * @param  list<string>  $defaultAdminRoles
     * @param  list<ExtensionRoleDefinition>  $pluginRoles  роли, объявленные расширением
     * @param  array<string, string>  $dependencies  extensionId => semver range
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public string $coreVersionRange,
        public string $tablePrefix,
        public string $manifestPath,
        public string $manifestHash,
        public ?string $providerClass,
        public ?string $backendRouteFile,
        public array $capabilityDefinitions,
        public array $defaultAdminRoles,
        public ?string $frontendBundle,
        public ?string $frontendMountPath,
        public ?string $frontendNavTitle,
        public ?string $frontendNavSection,
        public array $dependencies = [],
        public array $pluginRoles = [],
    ) {}
}
