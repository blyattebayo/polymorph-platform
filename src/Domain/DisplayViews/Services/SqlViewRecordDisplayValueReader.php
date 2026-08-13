<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DisplayViews\Services;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DisplayViews\Support\DisplayViewName;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;

class SqlViewRecordDisplayValueReader
{
    /**
     * @param  int[]  $recordIds
     * @return array<int, string>
     */
    public function read(RecordDefinition $recordDefinition, array $recordIds): array
    {
        if ($recordIds === []) {
            return [];
        }

        $viewName = DisplayViewName::forRecordDefinition((int) $recordDefinition->id);
        $placeholders = implode(', ', array_fill(0, count($recordIds), '?'));
        $rows = DB::select(
            sprintf(
                'SELECT id, display_value FROM %s WHERE id IN (%s)',
                DisplayViewName::quote($viewName),
                $placeholders,
            ),
            array_values($recordIds),
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->id] = isset($row->display_value)
                ? (string) $row->display_value
                : "Record #{$row->id}";
        }

        return $result;
    }
}
