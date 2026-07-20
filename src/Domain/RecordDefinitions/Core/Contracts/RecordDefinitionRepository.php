<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Репозиторий для работы с RecordDefinition.
 * 
 * Абстракция над БД для тестируемости и гибкости.
 */
interface RecordDefinitionRepository
{
    /**
     * Найти RecordDefinition по ID.
     */
    public function find(int $id): ?RecordDefinition;

   /**
    * Получить RecordDefinitions c пагинацией.
    *
    * @return LengthAwarePaginator<int, RecordDefinition>
    */
   public function paginate(PageRequest $pagination): LengthAwarePaginator;

    /**
     * Создать новый RecordDefinition.
     * 
     * @param array{
     *   name: string,
        *   display_template?: string|null,
        *   schema_id?: int|null
     * } $data
     */
    public function create(array $data): RecordDefinition;

    /**
     * Обновить RecordDefinition.
     * 
     * @param array{
     *   name?: string,
        *   display_template?: string|null,
        *   schema_id?: int|null
     * } $data
     */
    public function update(RecordDefinition $recordDefinition, array $data): RecordDefinition;

    /**
     * Удалить RecordDefinition.
     */
    public function delete(RecordDefinition $recordDefinition): void;

    /**
     * Краткие сведения о RecordDefinitions, использующих схему.
     *
     * @return list<array{id:int,name:string}>
     */
    public function summariesForSchema(int $schemaId): array;

    /**
     * Удалить ограничения ссылок на RecordDefinition перед force-удалением.
     */
    public function cleanupFieldRefConstraintsForForceDelete(RecordDefinition $recordDefinition): void;

}
