<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Manifest;

/**
 * Разобранный и провалидированный манифест расширения v2.
 */
final readonly class ManifestV2
{
    /** @param array<string, mixed> $contributes */
    public function __construct(
        public string $schemaVersion,
        public string $id,
        public string $name,
        public string $version,
        public string $sdk,
        public string $provider,
        public array $contributes,
    ) {}
}
