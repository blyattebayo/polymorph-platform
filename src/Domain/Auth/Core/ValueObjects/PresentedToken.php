<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Core\ValueObjects;

/**
 * Токен, предъявленный запросом, вместе с каналом доставки.
 *
 * Нейтрален к виду: и JWT, и PAT приезжают сюда одинаково, а кто из них кто —
 * решает CredentialAuthenticator::supports(). Раньше на этом месте была голая
 * строка, и «какой это токен» приходилось угадывать по префиксу прямо в
 * диспетчере.
 */
final readonly class PresentedToken
{
    private function __construct(
        /** @var non-empty-string */
        public string $value,
        public TokenTransport $transport,
    ) {}

    public static function bearer(string $value): ?self
    {
        return self::make($value, TokenTransport::Bearer);
    }

    public static function cookie(string $value): ?self
    {
        return self::make($value, TokenTransport::Cookie);
    }

    public function isBearer(): bool
    {
        return $this->transport === TokenTransport::Bearer;
    }

    public function isCookie(): bool
    {
        return $this->transport === TokenTransport::Cookie;
    }

    public function startsWith(string $prefix): bool
    {
        return $prefix !== '' && str_starts_with($this->value, $prefix);
    }

    private static function make(string $value, TokenTransport $transport): ?self
    {
        $value = trim($value);

        return $value === '' ? null : new self($value, $transport);
    }
}
