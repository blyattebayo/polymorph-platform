<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Console;

use Illuminate\Console\Command;
use Polymorph\Platform\Domain\DataPlatform\Migration\SchemaMigrationRunner;

final class RunSchemaMigrationCommand extends Command
{
    protected $signature = 'data-platform:schema-migrate {plan} {--batch=200} {--dry-run}';

    protected $description = 'Run or dry-run one resumable schema migration batch.';

    public function handle(SchemaMigrationRunner $runner): int
    {
        $result = $runner->runBatch((string) $this->argument('plan'), max(1, (int) $this->option('batch')), (bool) $this->option('dry-run'));
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
