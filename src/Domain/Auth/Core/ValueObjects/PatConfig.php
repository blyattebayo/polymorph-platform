<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Core\ValueObjects;

/**
 * Настройки персональных токенов — зеркало {@see JwtConfig} для второго способа
 * аутентификации.
 *
 * До этого у PAT собственного конфиг-объекта не было вовсе: восемь чтений
 * config('pat.*') россыпью, из них три — префикса в одном классе, каждое со
 * своим литеральным дефолтом.
 */
final readonly class PatConfig
{
    public function __construct(
        /** Принимать ли PAT при аутентификации. */
        public bool $enabled,
        /** Разрешён ли выпуск новых токенов (отзыв и просмотр остаются). */
        public bool $creationEnabled,
        /** @var non-empty-string */
        public string $prefix,
        /** ISO-8601 duration либо null — «бессрочно». */
        public ?string $defaultTtl,
        /** @var list<string> */
        public array $ttlOptions,
    ) {}

    /**
     * @param  array<string, mixed>  $config  секция pat
     */
    public static function fromArray(array $config): self
    {
        $prefix = is_scalar($config['prefix'] ?? null) ? trim((string) $config['prefix']) : '';
        $defaultTtl = is_scalar($config['default_ttl'] ?? null) ? trim((string) $config['default_ttl']) : '';

        return new self(
            enabled: (bool) ($config['enabled'] ?? true),
            creationEnabled: (bool) ($config['creation_enabled'] ?? true),
            prefix: $prefix === '' ? 'pmph_pat_' : $prefix,
            defaultTtl: $defaultTtl === '' ? null : $defaultTtl,
            ttlOptions: self::normalizeTtlOptions($config['ttl_options'] ?? []),
        );
    }

    /**
     * Длина видимой части токена в списках: префикс плюс шесть символов —
     * достаточно, чтобы владелец узнал свой ключ, и мало, чтобы подобрать.
     */
    public function visiblePrefixLength(): int
    {
        return strlen($this->prefix) + 6;
    }

    /**
     * @return list<string>
     */
    private static function normalizeTtlOptions(mixed $options): array
    {
        return array_values(array_filter(
            array_map(
                static fn (mixed $ttl): string => is_scalar($ttl) ? trim((string) $ttl) : '',
                (array) $options,
            ),
            static fn (string $ttl): bool => $ttl !== '',
        ));
    }
}
