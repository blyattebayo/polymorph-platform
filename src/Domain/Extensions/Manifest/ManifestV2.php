<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Manifest;

/**
 * Разобранный и провалидированный манифест расширения v2.
 */
final readonly class ManifestV2
{
    /**
     * @param list<string> $scopes требуемые scope-разрешения ядра (least-privilege)
     * @param list<string> $egress разрешённые исходящие хосты
     * @param array<string, mixed> $contributes декларативные contribution points (capabilities/routes/data/navigation)
     */
    public function __construct(
        public string $schemaVersion,
        public string $id,
        public string $name,
        public string $version,
        public ExtensionKind $kind,
        public string $sdk,
        public array $scopes,
        public array $egress,
        public array $contributes,
        public ?string $provider = null,
    ) {
    }
}
