<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Materialization\Console\Commands;

use Polymorph\Platform\Domain\Materialization\Services\RecordIndexMaterializer;
use Illuminate\Console\Command;

/**
 * Аудит partial-индексов records: ищет невалидные (недостроенные/битые) индексы
 * и при --repair пересобирает их по затронутым определениям (drop invalid + реконсайл).
 */
final class RecordIndexesDoctorCommand extends Command
{
    protected $signature = 'records:indexes:doctor {--repair : Пересобрать невалидные индексы (drop + reconcile)}';

    protected $description = 'Audit (and optionally repair) records partial indexes';

    public function handle(RecordIndexMaterializer $materializer): int
    {
        $invalid = $materializer->invalidIndexes();

        if ($invalid === []) {
            $this->info('No invalid record indexes found.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('Found %d invalid record index(es):', count($invalid)));
        foreach ($invalid as $name => $definitionId) {
            $this->line("  - {$name} (record_definition_id={$definitionId})");
        }

        if (!$this->option('repair')) {
            $this->line('Run with --repair to drop and rebuild them.');

            return self::FAILURE;
        }

        $definitionIds = array_values(array_unique(array_values($invalid)));
        foreach ($definitionIds as $definitionId) {
            $materializer->syncDefinition($definitionId);
            $this->info("Reconciled indexes for record_definition_id={$definitionId}.");
        }

        $remaining = $materializer->invalidIndexes();
        if ($remaining !== []) {
            $this->error(sprintf('%d index(es) still invalid after repair.', count($remaining)));

            return self::FAILURE;
        }

        $this->info('All invalid indexes repaired.');

        return self::SUCCESS;
    }
}
