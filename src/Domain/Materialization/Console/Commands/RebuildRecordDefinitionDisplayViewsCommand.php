<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Materialization\Console\Commands;

use Illuminate\Console\Command;
use Polymorph\Platform\Domain\Materialization\Services\RecordDefinitionViewManager;

final class RebuildRecordDefinitionDisplayViewsCommand extends Command
{
    protected $signature = 'projection:rebuild-views';

    protected $description = 'Rebuild SQL display views for all record definitions';

    public function handle(RecordDefinitionViewManager $viewManager): int
    {
        $count = $viewManager->rebuildAll();

        $this->info("Rebuilt {$count} record definition display views.");

        return self::SUCCESS;
    }
}
