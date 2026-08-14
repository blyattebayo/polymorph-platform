<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Migration;

use Polymorph\Platform\Domain\DataPlatform\Access\TrustedMaintenanceDataAccessPolicy;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformInvariantViolation;
use Polymorph\Platform\Domain\DataPlatform\Write\RecordCommandBus;
use Polymorph\Platform\Domain\DataPlatform\Write\RecordWriteCommand;
use Polymorph\Platform\Domain\DataPlatform\Write\RecordWriteResult;

final class MaintenanceRecordCommandBus
{
    private readonly RecordCommandBus $bus;

    public function __construct(RecordCommandBus $bus)
    {
        $this->bus = $bus->withAccessPolicy(new TrustedMaintenanceDataAccessPolicy);
    }

    public function dispatch(RecordWriteCommand $command): RecordWriteResult
    {
        if (! $command->schemaMigration) {
            throw DataPlatformInvariantViolation::because(
                'maintenance_bus_command_rejected',
                'Maintenance bus accepts schema-migration commands only.',
            );
        }

        return $this->bus->dispatch($command);
    }
}
