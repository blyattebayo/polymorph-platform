<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Core\ValueObjects;

/**
 * Чем клиент принёс токен. Не путать с CredentialKind: там — что за учётные
 * данные, здесь — по какому каналу они приехали. Транспорт важен ровно там, где
 * поведение зависит от канала: куку продлевает сервер, Bearer клиент носит сам.
 */
enum TokenTransport: string
{
    case Bearer = 'bearer';

    case Cookie = 'cookie';
}
