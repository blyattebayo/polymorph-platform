<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Console;

use Illuminate\Console\Command;
use Polymorph\Platform\Domain\DataPlatform\Projection\ProjectionRebuilder;

final class RebuildProjectionsCommand extends Command
{
    protected $signature = 'data-platform:projection-rebuild {definition : Record definition ID} {--batch=200} {--dry-run}';

    protected $description = 'Verify or rebuild deterministic record projections.';

    public function handle(ProjectionRebuilder $rebuilder): int
    {
        $result = $rebuilder->rebuildDefinition(
            (int) $this->argument('definition'),
            max(1, (int) $this->option('batch')),
            (bool) $this->option('dry-run'),
        );
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
