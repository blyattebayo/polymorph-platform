<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\EntryView\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Polymorph\Platform\Domain\EntryView\Core\Models\EntryView;

/**
 * Событие: конфигурация формы создана.
 *
 * Отправляется после успешного создания новой конфигурации.
 * Используется для логирования и аудита.
 */
final class EntryViewCreated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  EntryView  $entryView  Созданная конфигурация
     */
    public function __construct(
        public readonly EntryView $entryView,
    ) {}
}
