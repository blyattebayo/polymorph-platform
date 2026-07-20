<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Manifest;

use Polymorph\Platform\Support\Validation\ValidationConstraints;
use Polymorph\Sdk\Version\Compatibility;
use Polymorph\Sdk\Version\Sdk;
use Polymorph\Sdk\Version\SdkVersion;

/**
 * Валидатор манифеста расширения v2 + согласование версии SDK.
 *
 * Связывает version-машинерию SDK (Epic 0) с lifecycle: расширение объявляет
 * требуемый диапазон `sdk` (напр. "^2"), хост проверяет его против собственной
 * версии {@see Sdk::VERSION} через {@see Compatibility::satisfiesRange()} и
 * отклоняет несовместимое РАНЬШЕ загрузки кода.
 */
final class ManifestV2Validator
{
    /**
     * @param array<string, mixed> $manifest
     */
    public function validate(array $manifest, string $source = 'manifest'): ManifestV2
    {
        $id = $this->requireString($manifest, 'id', $source);
        if (!ValidationConstraints::slug()->matches($id)) {
            throw new \InvalidArgumentException("{$source}: id must match " . ValidationConstraints::slug()->pattern() . '.');
        }

        $name = $this->requireString($manifest, 'name', $source);
        $version = $this->requireString($manifest, 'version', $source);
        $this->assertSemver($version, $source);

        $schemaVersion = $this->requireString($manifest, 'schemaVersion', $source);

        $kindValue = is_string($manifest['kind'] ?? null) && trim((string) $manifest['kind']) !== ''
            ? trim((string) $manifest['kind'])
            : ExtensionKind::PLUGIN->value;
        $kind = ExtensionKind::tryFrom($kindValue)
            ?? throw new \InvalidArgumentException("{$source}: kind '{$kindValue}' is invalid (plugin|module|integration).");

        $sdk = $this->requireString($manifest, 'sdk', $source);
        if (!Compatibility::satisfiesRange(Sdk::VERSION, $sdk)) {
            throw new \InvalidArgumentException(
                "{$source}: requires SDK '{$sdk}', host SDK is " . Sdk::VERSION . ' (incompatible).',
            );
        }

        return new ManifestV2(
            schemaVersion: $schemaVersion,
            id: $id,
            name: $name,
            version: $version,
            kind: $kind,
            sdk: $sdk,
            scopes: $this->stringList($manifest['scopes'] ?? null),
            egress: $this->stringList($manifest['egress'] ?? null),
            contributes: is_array($manifest['contributes'] ?? null) ? $manifest['contributes'] : [],
            provider: is_string($manifest['provider'] ?? null) && trim((string) $manifest['provider']) !== ''
                ? trim((string) $manifest['provider'])
                : null,
        );
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function requireString(array $manifest, string $field, string $source): string
    {
        $value = $manifest[$field] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException("{$source}: field '{$field}' must be a non-empty string.");
        }

        return trim($value);
    }

    private function assertSemver(string $version, string $source): void
    {
        try {
            SdkVersion::fromString($version);
        } catch (\InvalidArgumentException) {
            throw new \InvalidArgumentException("{$source}: version '{$version}' is not semver (major.minor.patch).");
        }
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn ($v): string => is_string($v) ? trim($v) : '', $value),
            static fn (string $v): bool => $v !== '',
        ));
    }
}
