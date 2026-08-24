<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Core;

/**
 * Кому принадлежит конфигурация — единственное различие, которое хранилище знает
 * про свои строки.
 *
 * Общая одна на весь экземпляр и правится только системным администратором;
 * личная принадлежит автору запроса, и её идентичность включает автора, поэтому
 * чужую нельзя ни прочитать, ни перезаписать.
 */
enum UiConfigDomain: string
{
    case GLOBAL = 'global';
    case USER = 'user';

    public function isGlobal(): bool
    {
        return $this === self::GLOBAL;
    }
}
