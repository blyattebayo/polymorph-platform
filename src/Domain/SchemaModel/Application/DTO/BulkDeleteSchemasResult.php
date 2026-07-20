<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Application\DTO;

final readonly class BulkDeleteSchemasResult
{
    /**
     * @param list<int> $deleted
     * @param list<array{id:int,name:string,code:string,usage_count:int,record_definitions:array<int,array{id:int,name:string}>}> $blocked
     * @param list<int> $notFound
     * @param list<int> $failed Идентификаторы, удаление которых упало с непредвиденной ошибкой
     */
    public function __construct(
        public array $deleted,
        public array $blocked,
        public array $notFound,
        public array $failed,
    ) {
    }
}
