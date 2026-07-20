<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Manifest;

/**
 * Тип расширения. «plugin» — частный случай; SDK обслуживает и внутренние модули,
 * и партнёрские интеграции (см. ADR 0002).
 */
enum ExtensionKind: string
{
    case PLUGIN = 'plugin';
    case MODULE = 'module';
    case INTEGRATION = 'integration';
}
