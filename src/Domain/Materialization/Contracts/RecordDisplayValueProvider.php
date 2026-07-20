<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Materialization\Contracts;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;

interface RecordDisplayValueProvider
{
    /**
     * @param int[] $recordIds
     * @return array<int, string>
     */
    public function getDisplayValues(RecordDefinition $recordDefinition, array $recordIds): array;
}