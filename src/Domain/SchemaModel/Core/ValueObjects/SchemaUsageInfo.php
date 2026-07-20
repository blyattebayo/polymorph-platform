<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects;

/**
 * Единый источник правды об использовании схемы в RecordDefinition.
 *
 * Собирается один раз (см. SchemaRepository::getUsageInfo) и знает, как
 * представить себя для всех поверхностей: конфликт 409 одиночного удаления,
 * элемент blocked[] массового удаления и ответ usage-эндпоинта. Это исключает
 * расхождение формата и дублирование правила «схема используется».
 */
final readonly class SchemaUsageInfo
{
    /**
     * @param list<array{id:int,name:string}> $recordDefinitions
     */
    public function __construct(
        public int $schemaId,
        public string $schemaCode,
        public string $schemaName,
        public array $recordDefinitions,
    ) {
    }

    public function usageCount(): int
    {
        return count($this->recordDefinitions);
    }

    public function isInUse(): bool
    {
        return $this->recordDefinitions !== [];
    }

    /**
     * @return list<string>
     */
    public function reasons(): array
    {
        $count = $this->usageCount();

        return [
            'is used in ' . $count . ' record ' . ($count === 1 ? 'definition' : 'definitions'),
        ];
    }

    /**
     * Элемент blocked[] массового удаления (контракт зафиксирован FE).
     *
     * @return array{id:int,name:string,code:string,usage_count:int,record_definitions:list<array{id:int,name:string}>}
     */
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

    /**
     * Метаданные конфликта 409 одиночного удаления (reasons читает FE onError).
     *
     * @return array{schema_id:int,schema_code:string,usage_count:int,record_definitions:list<array{id:int,name:string}>,reasons:list<string>}
     */
    public function toConflictMeta(): array
    {
        return [
            'schema_id' => $this->schemaId,
            'schema_code' => $this->schemaCode,
            'usage_count' => $this->usageCount(),
            'record_definitions' => $this->recordDefinitions,
            'reasons' => $this->reasons(),
        ];
    }

    /**
     * Ответ usage-эндпоинта.
     *
     * @return array{schema_id:int,is_in_use:bool,usage_count:int,record_definitions:list<array{id:int,name:string}>}
     */
    public function toUsageResponse(): array
    {
        return [
            'schema_id' => $this->schemaId,
            'is_in_use' => $this->isInUse(),
            'usage_count' => $this->usageCount(),
            'record_definitions' => $this->recordDefinitions,
        ];
    }
}
