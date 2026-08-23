<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Services;

use Polymorph\Platform\Domain\UiConfig\Core\ConfigNamespace;
use Polymorph\Platform\Domain\UiConfig\Core\EntryViewConfigKey;
use Polymorph\Platform\Domain\UiConfig\Infrastructure\UiConfigRepository;

/**
 * Макет карточки переживает своего владельца ровно до конца его удаления:
 * ссылочную целостность здесь обеспечивает приложение, а не внешний ключ.
 */
final class ConfigCleaner
{
    public function __construct(
        private readonly UiConfigRepository $configs,
    ) {}

    public function removeForRecordDefinition(int $recordDefinitionId): int
    {
        return $this->configs->purge(
            ConfigNamespace::ENTRY_VIEW->value,
            EntryViewConfigKey::ofRecordDefinition($recordDefinitionId),
        );
    }

    public function removeForSchema(int $schemaId): int
    {
        return $this->configs->purge(
            ConfigNamespace::ENTRY_VIEW->value,
            EntryViewConfigKey::ofSchema($schemaId),
        );
    }
}
