<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Infrastructure\Repositories;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts\RecordDefinitionDependencyChecker;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Illuminate\Support\Facades\DB;

final class EloquentRecordDefinitionDependencyChecker implements RecordDefinitionDependencyChecker
{
    public function getRecordsCount(RecordDefinition $recordDefinition): int
    {
        return DB::table('records')
            ->where('record_definition_id', $recordDefinition->id)
            ->count();
    }

    public function getFieldRefConstraintsCount(RecordDefinition $recordDefinition): int
    {
        return DB::table('field_ref_constraints')
            ->where('allowed_record_definition_id', $recordDefinition->id)
            ->count();
    }

    public function getFieldRefConstraintPaths(RecordDefinition $recordDefinition): array
    {
        return DB::table('field_ref_constraints as frc')
            ->join('fields as f', 'f.id', '=', 'frc.field_id')
            ->join('schemas as s', 's.id', '=', 'f.schema_id')
            ->where('frc.allowed_record_definition_id', $recordDefinition->id)
            ->select('s.code as schema_code', 'f.full_path')
            ->get()
            ->map(static function (object $row): string {
                return "{$row->schema_code}.{$row->full_path}";
            })
            ->unique()
            ->values()
            ->toArray();
    }
}
