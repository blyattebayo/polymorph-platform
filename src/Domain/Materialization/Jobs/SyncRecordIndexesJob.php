<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Materialization\Jobs;

use Polymorph\Platform\Domain\Materialization\Services\RecordIndexMaterializer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

final class SyncRecordIndexesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly ?int $schemaId,
        private readonly ?int $definitionId = null,
        private readonly ?int $definitionSchemaId = null,
    ) {
    }

    public function handle(RecordIndexMaterializer $materializer): void
    {
        $concurrent = DB::transactionLevel() === 0;

        if ($this->definitionId !== null) {
            $materializer->syncDefinition($this->definitionId, $this->definitionSchemaId, $concurrent);

            return;
        }

        if ($this->schemaId !== null) {
            $materializer->syncSchema($this->schemaId, $concurrent);
        }
    }
}
