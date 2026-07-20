<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Polymorph\Platform\Domain\Extensions\Core\Models\ExtensionRegistry;
use Polymorph\Platform\Domain\Extensions\Core\ValueObjects\DiscoveredExtension;

final class ExtensionRegistryService
{
    /**
     * @param  list<DiscoveredExtension>  $plugins
     */
    public function syncDiscoveredPlugins(array $plugins): void
    {
        $syncedAt = Carbon::now();

        foreach ($plugins as $plugin) {
            ExtensionRegistry::query()->updateOrCreate(
                ['plugin_id' => $plugin->id],
                [
                    'name' => $plugin->name,
                    'version' => $plugin->version,
                    'core_version_range' => $plugin->coreVersionRange,
                    'manifest_hash' => $plugin->manifestHash,
                    'manifest_path' => $plugin->manifestPath,
                    'provider_class' => $plugin->providerClass,
                    'frontend_bundle' => $plugin->frontendBundle,
                    'last_synced_at' => $syncedAt,
                ],
            );
        }
    }

    /**
     * @param  list<DiscoveredExtension>  $plugins
     */
    public function syncDiscoveredExtensions(array $plugins): void
    {
        $this->syncDiscoveredPlugins($plugins);
    }

    public function markEnabled(string $pluginId): ExtensionRegistry
    {
        $entry = $this->requireById($pluginId);
        $entry->is_enabled = true;
        $entry->enabled_at = Carbon::now();
        $entry->disabled_at = null;
        $entry->save();
        $entry->refresh();

        return $entry;
    }

    public function markDisabled(string $pluginId): ExtensionRegistry
    {
        $entry = $this->requireById($pluginId);
        $entry->is_enabled = false;
        $entry->disabled_at = Carbon::now();
        $entry->save();
        $entry->refresh();

        return $entry;
    }

    public function findEnabled(string $pluginId): ?ExtensionRegistry
    {
        return ExtensionRegistry::query()
            ->where('plugin_id', $pluginId)
            ->where('is_enabled', true)
            ->first();
    }

    public function findById(string $pluginId): ?ExtensionRegistry
    {
        return ExtensionRegistry::query()->where('plugin_id', $pluginId)->first();
    }

    public function requireById(string $pluginId): ExtensionRegistry
    {
        $entry = $this->findById($pluginId);
        if (! $entry instanceof ExtensionRegistry) {
            throw new \InvalidArgumentException("Plugin '{$pluginId}' is not registered.");
        }

        return $entry;
    }

    public function deleteById(string $pluginId): void
    {
        ExtensionRegistry::query()->where('plugin_id', $pluginId)->delete();
    }

    /**
     * @return EloquentCollection<int, ExtensionRegistry>
     */
    public function all(): EloquentCollection
    {
        return ExtensionRegistry::query()->orderBy('plugin_id')->get();
    }
}
