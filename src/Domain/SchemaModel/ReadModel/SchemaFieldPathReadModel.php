<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\ReadModel;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldType;
use Polymorph\Platform\Domain\SchemaModelValidation\FieldPathBuilder;

final class SchemaFieldPathReadModel
{
    public function __construct(
        private readonly FieldPathBuilder $pathBuilder,
    ) {}

    public function schemaPaths(int $schemaId): array
    {
        if ($schemaId <= 0) {
            return [
                'ref' => [],
                'media' => [],
            ];
        }

        return $this->schemaPathsBySchemaIds([$schemaId])[$schemaId] ?? [
            'ref' => [],
            'media' => [],
        ];
    }

    public function schemaPathsBySchemaIds(array $schemaIds): array
    {
        $schemaIds = array_values(array_unique(array_filter(array_map('intval', $schemaIds), static fn (int $schemaId): bool => $schemaId > 0)));

        if ($schemaIds === []) {
            return [];
        }

        /** @var Collection<int, object{schema_id:int,full_path:string,cardinality:string,type:string}> $rows */
        $rows = DB::table('fields')
            ->whereIn('schema_id', $schemaIds)
            ->select(['schema_id', 'full_path', 'cardinality', 'type'])
            ->get();

        $pathsBySchemaId = [];
        foreach ($schemaIds as $schemaId) {
            $pathsBySchemaId[$schemaId] = [
                'ref' => [],
                'media' => [],
            ];
        }

        if ($rows->isEmpty()) {
            return $pathsBySchemaId;
        }

        $rowsBySchemaId = $rows->groupBy(static fn (object $row): int => (int) $row->schema_id);

        foreach ($rowsBySchemaId as $schemaId => $schemaRows) {
            $pathCardinalities = $schemaRows
                ->mapWithKeys(static fn (object $row): array => [(string) $row->full_path => (string) $row->cardinality])
                ->all();

            $pathsBySchemaId[(int) $schemaId] = [
                'ref' => $this->extractTypedPaths($schemaRows, $pathCardinalities, FieldType::REF->value),
                'media' => $this->extractTypedPaths($schemaRows, $pathCardinalities, FieldType::MEDIA->value),
            ];
        }

        return $pathsBySchemaId;
    }

    /**
     * @param  Collection<int, object{schema_id:int,full_path:string,cardinality:string,type:string}>  $rows
     * @param  array<string, string>  $pathCardinalities
     * @return string[]
     */
    private function extractTypedPaths(Collection $rows, array $pathCardinalities, string $fieldType): array
    {
        return $rows
            ->filter(static fn (object $row): bool => (string) $row->type === $fieldType)
            ->map(function (object $row) use ($pathCardinalities): string {
                return $this->pathBuilder->computeDataPath(
                    (string) $row->full_path,
                    (string) $row->cardinality,
                    $pathCardinalities,
                    (string) $row->type === FieldType::JSON->value,
                );
            })
            ->filter(static fn (string $path): bool => $path !== '')
            ->unique()
            ->values()
            ->all();
    }
}
