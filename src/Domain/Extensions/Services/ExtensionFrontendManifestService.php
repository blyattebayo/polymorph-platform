<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Services;

use Polymorph\Platform\Domain\Extensions\Core\ValueObjects\DiscoveredExtension;

final class ExtensionFrontendManifestService
{
    public function __construct(
        private readonly ExtensionDiscoveryService $discovery,
    ) {}

    /** @return list<array<string, mixed>> */
    public function frontendPlugins(): array
    {
        return array_values(array_map(
            static fn (DiscoveredExtension $extension): array => [
                'id' => $extension->id,
                'name' => $extension->name,
                'version' => $extension->version,
                'requiredContractVersion' => $extension->sdkVersion,
                'bundle' => "/plugins/{$extension->id}/fe/plugin.js?v=".rawurlencode($extension->version),
                'mountPath' => "/plugins/{$extension->id}",
            ],
            array_filter(
                $this->discovery->discoverAll(),
                static fn (DiscoveredExtension $extension): bool => $extension->hasFrontend,
            ),
        ));
    }
}
