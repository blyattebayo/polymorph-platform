<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Write;

use Polymorph\Platform\Domain\DataPlatform\Access\TrustedMaintenanceDataAccessPolicy;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformInvariantViolation;

final class MaintenanceRecordCommandBus
{
    private readonly RecordCommandBus $bus;

    public function __construct(RecordCommandBus $bus)
    {
        $this->bus = $bus->withMaintenanceAccess(TrustedMaintenanceDataAccessPolicy::forMaintenanceCommands());
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
