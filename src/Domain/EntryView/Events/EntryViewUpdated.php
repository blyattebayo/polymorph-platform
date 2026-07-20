<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\EntryView\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Polymorph\Platform\Domain\EntryView\Core\Models\EntryView;

/**
 * Событие: конфигурация формы обновлена.
 *
 * Отправляется после успешного обновления существующей конфигурации.
 * Используется для логирования, аудита и инвалидации кеша.
 */
final class EntryViewUpdated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  EntryView  $entryView  Обновленная конфигурация
     */
    public function __construct(
        public readonly EntryView $entryView,
    ) {}
}
