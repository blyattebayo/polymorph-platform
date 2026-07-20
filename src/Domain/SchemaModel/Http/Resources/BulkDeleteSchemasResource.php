<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Http\Resources;

use Polymorph\Platform\Domain\SchemaModel\Application\DTO\BulkDeleteSchemasResult;

final class BulkDeleteSchemasResource
{
    /**
     * @return array{
     *     deleted: list<int>,
     *     blocked: list<array{id:int,name:string,code:string,usage_count:int,record_definitions:array<int,array{id:int,name:string}>}>,
     *     not_found: list<int>,
     *     failed: list<int>
     * }
     */
    public static function fromResult(BulkDeleteSchemasResult $result): array
    {
        return [
            'deleted' => $result->deleted,
            'blocked' => $result->blocked,
            'not_found' => $result->notFound,
            'failed' => $result->failed,
        ];
    }
}
