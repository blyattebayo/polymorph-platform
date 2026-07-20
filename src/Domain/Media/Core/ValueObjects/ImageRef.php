<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\ValueObjects;

/**
 * Opaque-хэндл изображения для разных бэкендов.
 *
 * Нельзя полагаться на конкретный тип $native вне драйвера.
 *
 * @template TNative
 */
final class ImageRef
{
    /**
     * @param  mixed  $native  Нативный объект/ресурс бэкенда
     */
    public function __construct(
        public readonly mixed $native
    ) {}
}
