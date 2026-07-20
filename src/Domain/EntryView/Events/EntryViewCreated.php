<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\EntryView\Events;

use Polymorph\Platform\Domain\EntryView\Core\Models\EntryView;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие: конфигурация формы создана.
 *
 * Отправляется после успешного создания новой конфигурации.
 * Используется для логирования и аудита.
 *
 * @package Polymorph\Platform\Domain\EntryView\Events
 */
final class EntryViewCreated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param EntryView $entryView Созданная конфигурация
     */
    public function __construct(
        public readonly EntryView $entryView,
    ) {
    }
}
