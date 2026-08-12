<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Manifest;

use Polymorph\Platform\Support\Validation\ValidationConstraints;
use Polymorph\Sdk\Version\Sdk;
use Polymorph\Sdk\Version\SdkVersion;

/**
 * Validates the one supported manifest shape and exact SDK version before code loads.
 */
final class ManifestV2Validator
{
    private const FIELDS = [
        'schemaVersion',
        'id',
        'name',
        'version',
        'sdk',
        'provider',
        'contributes',
    ];

    /**
     * @param  array<string, mixed>  $manifest
     */
    public function validate(array $manifest, string $source = 'manifest'): ManifestV2
    {
        $unknown = array_values(array_diff(array_keys($manifest), self::FIELDS));
        if ($unknown !== []) {
            throw new \InvalidArgumentException(
                "{$source}: unsupported field(s): ".implode(', ', $unknown).'.',
            );
        }

        $id = $this->requireString($manifest, 'id', $source);
        if (! ValidationConstraints::slug()->matches($id)) {
            throw new \InvalidArgumentException("{$source}: id must match ".ValidationConstraints::slug()->pattern().'.');
        }

        $name = $this->requireString($manifest, 'name', $source);
        $version = $this->requireString($manifest, 'version', $source);
        $this->assertSemver($version, $source);

        $schemaVersion = $this->requireString($manifest, 'schemaVersion', $source);
        if ($schemaVersion !== '2.0') {
            throw new \InvalidArgumentException("{$source}: schemaVersion must be '2.0'.");
        }

        $sdk = $this->requireString($manifest, 'sdk', $source);
        if ($sdk !== Sdk::VERSION) {
            throw new \InvalidArgumentException(
                "{$source}: sdk must equal host SDK ".Sdk::VERSION.", got '{$sdk}'.",
            );
        }

        $provider = $this->requireString($manifest, 'provider', $source);
        $contributes = $manifest['contributes'] ?? [];
        if (! is_array($contributes)) {
            throw new \InvalidArgumentException("{$source}: contributes must be an object.");
        }
        $unknownContributions = array_values(array_diff(
            array_keys($contributes),
            ['capabilities', 'roles', 'defaultAdminRoles'],
        ));
        if ($unknownContributions !== []) {
            throw new \InvalidArgumentException(
                "{$source}: unsupported contribution(s): ".implode(', ', $unknownContributions).'.',
            );
        }

        return new ManifestV2(
            schemaVersion: $schemaVersion,
            id: $id,
            name: $name,
            version: $version,
            sdk: $sdk,
            provider: $provider,
            contributes: $contributes,
        );
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function requireString(array $manifest, string $field, string $source): string
    {
        $value = $manifest[$field] ?? null;
        if (! is_string($value) || trim($value) === '') {
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

}
