<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DisplayViews\Console;

use Illuminate\Console\Command;
use Polymorph\Platform\Domain\DisplayViews\Services\RecordDefinitionDisplayViewSynchronizer;

final class RebuildRecordDefinitionDisplayViewsCommand extends Command
{
    protected $signature = 'projection:rebuild-views';

    protected $description = 'Rebuild SQL display views for all record definitions';

    public function handle(RecordDefinitionDisplayViewSynchronizer $synchronizer): int
    {
        $count = $synchronizer->rebuildAll();

        $this->info("Rebuilt {$count} record definition display views.");

        return self::SUCCESS;
    }
}
