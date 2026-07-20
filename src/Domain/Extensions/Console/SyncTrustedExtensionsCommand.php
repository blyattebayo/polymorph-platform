<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Console;

use Polymorph\Platform\Domain\Extensions\Services\ExtensionManager;
use Illuminate\Console\Command;

/**
 * Deploy-time provisioning for trusted (withPlugins) plugins shipped as Composer packages: runs
 * their migrations, ACL policies/roles and onEnable seeding idempotently, WITHOUT a plugins_registry
 * row (trusted plugins are always-on). Intended to run right after `migrate --force` on every deploy
 * (ADR 0006 Стадия 4). Marketplace/runtime plugins keep their admin enable/disable flow untouched.
 */
final class SyncTrustedExtensionsCommand extends Command
{
    protected $signature = 'plugins:sync-trusted';

    protected $description = 'Provision trusted (withPlugins) Composer-package plugins: migrations + ACL + onEnable seeding (idempotent).';

    public function handle(ExtensionManager $manager): int
    {
        $provisioned = $manager->provisionAllTrusted();

        if ($provisioned === []) {
            $this->info('No trusted plugins configured (config plugins.trusted / withPlugins).');

            return self::SUCCESS;
        }

        foreach ($provisioned as $id) {
            $this->line("  provisioned trusted plugin: {$id}");
        }
        $this->info(count($provisioned) . ' trusted plugin(s) provisioned.');

        return self::SUCCESS;
    }
}
