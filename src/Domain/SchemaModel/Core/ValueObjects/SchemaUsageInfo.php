<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects;

/** Immutable deletion decision input produced by SchemaRepository. */
final readonly class SchemaUsageInfo
{
    /** @param list<array{id:int,name:string}> $recordDefinitions */
    public function __construct(
        public int $schemaId,
        public string $schemaCode,
        public string $schemaName,
        public array $recordDefinitions,
    ) {}

    public function usageCount(): int
    {
        return count($this->recordDefinitions);
    }

    public function isInUse(): bool
    {
        return $this->recordDefinitions !== [];
    }

    /** @return array{id:int,name:string,code:string,usage_count:int,record_definitions:list<array{id:int,name:string}>} */
    public function toBlockedEntry(): array
    {
        return [
            'id' => $this->schemaId,
            'name' => $this->schemaName,
            'code' => $this->schemaCode,
            'usage_count' => $this->usageCount(),
            'record_definitions' => $this->recordDefinitions,
        ];
    }

    /** @return array{schema_id:int,schema_code:string,usage_count:int,record_definitions:list<array{id:int,name:string}>,reasons:list<string>} */
    public function toConflictMeta(): array
    {
        $count = $this->usageCount();

        return [
            'schema_id' => $this->schemaId,
            'schema_code' => $this->schemaCode,
            'usage_count' => $count,
            'record_definitions' => $this->recordDefinitions,
            'reasons' => ['is used in '.$count.' record '.($count === 1 ? 'definition' : 'definitions')],
        ];
    }
}
