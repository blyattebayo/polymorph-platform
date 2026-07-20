<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\EntryView\Queries;

use Polymorph\Platform\Domain\EntryView\Core\Contracts\EntryViewRepository;
use Polymorph\Platform\Domain\EntryView\Core\Models\EntryView;

/**
 * Query для поиска конфигурации формы по RecordDefinition и Schema.
 *
 * Инкапсулирует логику поиска конфигурации,
 * изолируя её от HTTP слоя.
 */
final readonly class FindEntryViewQuery
{
    /**
     * Конструктор.
     *
     * @param  EntryViewRepository  $repository  Репозиторий конфигураций
     */
    public function __construct(
        private EntryViewRepository $repository
    ) {}

    /**
     * Выполнить поиск конфигурации.
     *
     * @param  int  $recordDefinitionId  ID типа контента
     * @param  int  $schemaId  ID схемы
     * @return EntryView|null Найденная конфигурация или null
     */
    public function execute(int $recordDefinitionId, int $schemaId): ?EntryView
    {
        return $this->repository->findByRecordDefinitionAndSchema($recordDefinitionId, $schemaId);
    }
}
