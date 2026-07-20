<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;

interface RecordDefinitionDependencyChecker
{
    public function getRecordsCount(RecordDefinition $recordDefinition): int;

    public function getFieldRefConstraintsCount(RecordDefinition $recordDefinition): int;

    /**
     * @return array<int, string>
     */
    public function getFieldRefConstraintPaths(RecordDefinition $recordDefinition): array;
}
