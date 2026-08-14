<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Control;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformResourceNotFound;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformStateConflict;

final class DefinitionDeleteService
{
    public function delete(int $definitionId): void
    {
        DB::transaction(function () use ($definitionId): void {
            $definition = DB::table('dp_record_definitions')->where('id', $definitionId)->lockForUpdate()->first();
            if ($definition === null) {
                throw DataPlatformResourceNotFound::for('record-definition', $definitionId);
            }
            if (DB::table('dp_records')->where('record_definition_id', $definitionId)->exists()) {
                throw DataPlatformStateConflict::because(
                    'definition_has_records',
                    'Definitions with records cannot be deleted.',
                    ['record_definition_id' => $definitionId],
                );
            }
            DB::table('dp_record_definitions')->where('id', $definitionId)->delete();
        });
    }
}
