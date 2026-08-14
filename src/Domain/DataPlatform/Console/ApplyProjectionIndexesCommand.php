<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Console;

use Illuminate\Console\Command;
use Polymorph\Platform\Domain\DataPlatform\Projection\ProjectionDefinitionService;

final class ApplyProjectionIndexesCommand extends Command
{
    protected $signature = 'data-platform:projection-indexes {schema? : Schema version ULID} {--limit=20} {--dry-run}';

    protected $description = 'Apply pending versioned Data Platform expression indexes.';

    public function handle(ProjectionDefinitionService $projections): int
    {
        $result = $projections->applyPending(
            $this->argument('schema') === null ? null : (string) $this->argument('schema'),
            max(1, (int) $this->option('limit')),
            (bool) $this->option('dry-run'),
        );
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
