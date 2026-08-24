<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Services;

use Polymorph\Platform\Domain\UiConfig\Core\EntryViewConfigKey;
use Polymorph\Platform\Domain\UiConfig\Infrastructure\UiConfigSlot;
use Polymorph\Platform\Domain\UiConfig\Infrastructure\UiConfigStore;

/**
 * Макет карточки переживает своего владельца ровно до конца его удаления:
 * ссылочную целостность здесь обеспечивает приложение, а не внешний ключ.
 *
 * Владелец один — тип контента. Схема в идентичность макета не входит, поэтому
 * ни её замена, ни её удаление макета не касаются.
 */
final class UiConfigCleaner
{
    public function __construct(
        private readonly UiConfigStore $configs,
    ) {}

    public function removeForRecordDefinition(int $recordDefinitionId): int
    {
        return $this->configs->deleteByIdentity(
            UiConfigSlot::global(EntryViewConfigKey::for($recordDefinitionId)),
        );
    }
}
