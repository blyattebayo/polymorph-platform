<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Infrastructure;

use Polymorph\Platform\PipelineCore\Locking\LockKey;

/**
 * Адрес ячейки хранения UI-конфига: значения колонок идентичности и ключ лока.
 *
 * Идентичность нарочно описана один раз: из неё выводится и запрос, и набор
 * полей записи. Раньше эти два места расходились по репозиториям.
 */
final readonly class UiConfigSlot
{
    /**
     * @param  array<string, mixed>  $identity
     */
    public function __construct(
        public array $identity,
        public LockKey $lock,
    ) {}
}
