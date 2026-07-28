<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Core\ValueObjects;

/**
 * Расширение, которое не удалось прочитать при обходе каталога.
 *
 * Пропущенное расширение обязано оставлять след: «поставил, а его нет» —
 * ровно та ситуация, из-за которой обход раньше падал целиком, роняя
 * приложение вместе с собой.
 */
final readonly class ExtensionDiscoveryFailure
{
    public function __construct(
        public string $path,
        public string $reason,
    ) {}
}
