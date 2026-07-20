<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\EntryView\Services;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\EntryView\Core\Contracts\EntryViewRepository;
use Polymorph\Platform\Domain\EntryView\Core\Models\EntryView;
use Polymorph\Platform\Domain\EntryView\Events\EntryViewCreated;
use Polymorph\Platform\Domain\EntryView\Events\EntryViewUpdated;

final class EntryViewService
{
    public function __construct(
        private readonly EntryViewRepository $repository,
    ) {}

    /**
     * Создать или обновить конфигурацию формы.
     */
    public function upsert(int $recordDefinitionId, int $schemaId, array $configJson): EntryView
    {
        return DB::transaction(function () use ($recordDefinitionId, $schemaId, $configJson) {
            $existing = $this->repository->findByRecordDefinitionAndSchema($recordDefinitionId, $schemaId);

            $saved = $this->repository->createOrUpdate($recordDefinitionId, $schemaId, $configJson);

            if ($existing) {
                EntryViewUpdated::dispatch($saved);
            } else {
                EntryViewCreated::dispatch($saved);
            }

            return $saved;
        });
    }
}
