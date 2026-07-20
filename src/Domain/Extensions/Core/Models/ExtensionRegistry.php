<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Core\Models;

use Illuminate\Database\Eloquent\Model;

final class ExtensionRegistry extends Model
{
    protected $table = 'plugins_registry';

    protected $fillable = [
        'plugin_id',
        'name',
        'version',
        'core_version_range',
        'is_enabled',
        'manifest_hash',
        'manifest_path',
        'provider_class',
        'frontend_bundle',
        'last_error',
        'last_warning',
        'enabled_at',
        'disabled_at',
        'last_synced_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'enabled_at' => 'datetime',
        'disabled_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];
}
