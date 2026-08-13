<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordIndexes\Console;

use Illuminate\Console\Command;
use Polymorph\Platform\Domain\RecordIndexes\Services\RecordIndexReconciler;
use Polymorph\Platform\Domain\RecordIndexes\Services\RecordIndexReconciliationProcessor;
use Polymorph\Platform\Domain\RecordIndexes\Services\RecordIndexReconciliationRequestStore;

/**
 * Аудит partial-индексов records: ищет невалидные (недостроенные/битые) индексы
 * и при --repair пересобирает их по затронутым определениям (drop invalid + реконсайл).
 */
final class RecordIndexesDoctorCommand extends Command
{
    protected $signature = 'records:indexes:doctor {--repair : Пересобрать невалидные индексы (drop + reconcile)}';

    protected $description = 'Audit (and optionally repair) records partial indexes';

    public function handle(
        RecordIndexReconciler $reconciler,
        RecordIndexReconciliationRequestStore $requests,
        RecordIndexReconciliationProcessor $processor,
    ): int {
        $invalid = $reconciler->invalidIndexes();
        $pending = $requests->pending();

        if ($invalid === [] && $pending === []) {
            $this->info('No invalid indexes or pending reconciliation requests found.');

            return self::SUCCESS;
        }

        if ($invalid !== []) {
            $this->warn(sprintf('Found %d invalid record index(es):', count($invalid)));
            foreach ($invalid as $name => $definitionId) {
                $this->line("  - {$name} (record_definition_id={$definitionId})");
            }
        }
        if ($pending !== []) {
            $this->warn(sprintf('Found %d pending reconciliation request(s):', count($pending)));
            foreach ($pending as $request) {
                $this->line("  - request={$request->id} {$request->targetType}={$request->targetId} generation={$request->generation}");
            }
        }

        if (! $this->option('repair')) {
            $this->line('Run with --repair to process pending requests and rebuild invalid indexes.');

            return self::FAILURE;
        }

        foreach ($pending as $request) {
            $processor->process($request);
            $this->info("Processed reconciliation request {$request->id}.");
        }

        $definitionIds = array_values(array_unique(array_values($invalid)));
        foreach ($definitionIds as $definitionId) {
            $reconciler->reconcileDefinition($definitionId);
            $this->info("Reconciled indexes for record_definition_id={$definitionId}.");
        }

        $remaining = $reconciler->invalidIndexes();
        $remainingPending = $requests->pending();
        if ($remaining !== [] || $remainingPending !== []) {
            $this->error(sprintf(
                '%d index(es) and %d request(s) still require attention after repair.',
                count($remaining),
                count($remainingPending),
            ));

            return self::FAILURE;
        }

        $this->info('All pending requests processed and invalid indexes repaired.');

        return self::SUCCESS;
    }
}
