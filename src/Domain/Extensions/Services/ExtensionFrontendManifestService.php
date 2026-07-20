<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Services;

use Polymorph\Platform\Domain\Extensions\Core\Models\ExtensionRegistry;

final class ExtensionFrontendManifestService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function enabledFrontendPlugins(): array
    {
        return ExtensionRegistry::query()
            ->where('is_enabled', true)
            ->whereNotNull('frontend_bundle')
            ->orderBy('plugin_id')
            ->get()
            ->map(static function (ExtensionRegistry $plugin): array {
                $frontend = self::frontendManifestData($plugin);

                $entry = [
                    'id' => $plugin->plugin_id,
                    'name' => $plugin->name,
                    'version' => $plugin->version,
                    'bundle' => self::versionedBundle($frontend['bundle'], (string) $plugin->version),
                    'navigation' => [
                        'title' => $frontend['navigation']['title'] ?? $plugin->name,
                        'section' => $frontend['navigation']['section'] ?? config('plugins.default_frontend_section', 'content'),
                    ],
                    'mountPath' => $frontend['mountPath'] ?? "/plugins/{$plugin->plugin_id}",
                    'ui' => [
                        'mode' => $frontend['uiMode'] ?? config('plugins.default_frontend_ui_mode', 'overlay'),
                    ],
                ];

                if (is_string($frontend['contractVersion'])) {
                    $entry['requiredContractVersion'] = $frontend['contractVersion'];
                }

                return $entry;
            })
            ->values()
            ->all();
    }

    private static function versionedBundle(?string $bundle, string $version): ?string
    {
        if (! is_string($bundle) || $bundle === '' || ! str_starts_with($bundle, '/')) {
            return $bundle;
        }

        $version = trim($version);
        if ($version === '') {
            return $bundle;
        }

        $separator = str_contains($bundle, '?') ? '&' : '?';

        return $bundle . $separator . 'v=' . rawurlencode($version);
    }

    /**
     * @return array{
     *   contractVersion:?string,
     *   mountPath:?string,
     *   bundle:?string,
     *   navigation:array{title?:string, section?:string},
     *   uiMode:?string
     * }
     */
    private static function frontendManifestData(ExtensionRegistry $plugin): array
    {
        $manifestPath = (string) $plugin->manifest_path;
        if ($manifestPath === '' || ! is_file($manifestPath)) {
            return [
                'contractVersion' => null,
                'mountPath' => null,
                'bundle' => is_string($plugin->frontend_bundle) ? trim((string) $plugin->frontend_bundle) : null,
                'navigation' => [],
                'uiMode' => null,
            ];
        }

        $raw = file_get_contents($manifestPath);
        if (! is_string($raw) || trim($raw) === '') {
            return [
                'contractVersion' => null,
                'mountPath' => null,
                'bundle' => is_string($plugin->frontend_bundle) ? trim((string) $plugin->frontend_bundle) : null,
                'navigation' => [],
                'uiMode' => null,
            ];
        }

        $manifest = json_decode($raw, true);
        if (! is_array($manifest)) {
            return [
                'contractVersion' => null,
                'mountPath' => null,
                'bundle' => is_string($plugin->frontend_bundle) ? trim((string) $plugin->frontend_bundle) : null,
                'navigation' => [],
                'uiMode' => null,
            ];
        }

        $version = data_get($manifest, 'frontend.contractVersion');
        $mountPath = data_get($manifest, 'frontend.mountPath');
        $bundle = data_get($manifest, 'frontend.bundle');

        $uiMode = data_get($manifest, 'frontend.ui.mode');
        if ($uiMode === null) {
            $uiMode = data_get($manifest, 'contributes.frontend.ui.mode');
        }

        return [
            'contractVersion' => is_string($version) && trim($version) !== '' ? trim($version) : null,
            'mountPath' => is_string($mountPath) && trim($mountPath) !== '' ? trim($mountPath) : null,
            'bundle' => is_string($bundle) && trim($bundle) !== '' ? trim($bundle) : (is_string($plugin->frontend_bundle) ? trim((string) $plugin->frontend_bundle) : null),
            'navigation' => [
                'title' => is_string(data_get($manifest, 'frontend.navigation.title'))
                    ? trim((string) data_get($manifest, 'frontend.navigation.title'))
                    : null,
                'section' => is_string(data_get($manifest, 'frontend.navigation.section'))
                    ? trim((string) data_get($manifest, 'frontend.navigation.section'))
                    : null,
            ],
            'uiMode' => is_string($uiMode) && trim($uiMode) !== '' ? trim($uiMode) : null,
        ];
    }
}
