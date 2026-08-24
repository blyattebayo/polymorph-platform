<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Core;

/**
 * Проверенная операция записи: адрес, заявленная ревизия, версия формата и сам
 * документ.
 *
 * Собирает её только слой валидации, поэтому сервису не нужно ни перепроверять
 * поля, ни знать, откуда операция приехала.
 */
final readonly class UiConfigWrite
{
    public function __construct(
        public string $key,
        public UiConfigDomain $domain,
        public int $revision,
        public int $version,
        public mixed $value,
    ) {}

    /**
     * Конфигурация уходит в хранилище как версия формата плюс значение: адрес в
     * документ не попадает. Порядок ключей внутри значения сохраняет json_decode.
     */
    public function configJson(): string
    {
        return (string) json_encode(
            ['version' => $this->version, 'value' => $this->value],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        );
    }
}
