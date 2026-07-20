<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Http\Resources;

use Polymorph\Platform\Domain\Extensions\Core\Models\ExtensionRegistry;
use Polymorph\Platform\Http\Resources\Admin\AdminJsonResource;

final class ExtensionResource extends AdminJsonResource
{
    public function toArray($request): array
    {
        /** @var ExtensionRegistry $plugin */
        $plugin = $this->resource;

        return [
            'id' => $plugin->plugin_id,
            'name' => $plugin->name,
            'version' => $plugin->version,
            'core_version_range' => $plugin->core_version_range,
            'enabled' => (bool) $plugin->is_enabled,
            'manifest_path' => $plugin->manifest_path,
            'frontend' => [
                'bundle' => $plugin->frontend_bundle,
            ],
            'last_error' => $plugin->last_error,
            'last_warning' => $plugin->last_warning,
            'enabled_at' => $plugin->enabled_at?->toIso8601String(),
            'disabled_at' => $plugin->disabled_at?->toIso8601String(),
            'last_synced_at' => $plugin->last_synced_at?->toIso8601String(),
            'updated_at' => $plugin->updated_at?->toIso8601String(),
        ];
    }
}
