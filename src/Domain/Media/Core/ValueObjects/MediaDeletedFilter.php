<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\ValueObjects;

/**
 * Управление выборкой с учётом soft deletes.
 */
enum MediaDeletedFilter: string
{
    case DefaultOnlyNotDeleted = 'default';
    case WithDeleted = 'with';
    case OnlyDeleted = 'only';

    /**
     * Создать из строкового значения с fallback.
     */
    public static function fromString(?string $value): self
    {
        if ($value === null) {
            return self::DefaultOnlyNotDeleted;
        }

        return match (strtolower($value)) {
            'with' => self::WithDeleted,
            'only' => self::OnlyDeleted,
            default => self::DefaultOnlyNotDeleted,
        };
    }
}
