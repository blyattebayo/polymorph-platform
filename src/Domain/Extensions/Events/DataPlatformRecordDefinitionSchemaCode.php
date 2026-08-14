<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Events;

use Illuminate\Support\Facades\DB;

final class DataPlatformRecordDefinitionSchemaCode implements RecordDefinitionSchemaCode
{
    public function forDefinition(int $recordDefinitionId): ?string
    {
        $code = DB::table('dp_record_definitions')->where('id', $recordDefinitionId)->value('code');

        return is_string($code) && $code !== '' ? $code : null;
    }
}
