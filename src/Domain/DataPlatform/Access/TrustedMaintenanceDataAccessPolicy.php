<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Access;

/** Internal-only authority used by explicitly registered schema-migration and repair commands. */
final class TrustedMaintenanceDataAccessPolicy implements DataAccessPolicy
{
    use UniformDataAccessPolicy;

    private function __construct() {}

    /** @internal Issued only to MaintenanceRecordCommandBus. */
    public static function forMaintenanceCommands(): self
    {
        return new self;
    }

    protected function grantsAllDataAccess(): bool
    {
        return true;
    }
}
