<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Access;

use InvalidArgumentException;

/**
 * Типизированный ACL-ресурс вместо голой строки.
 *
 * Формат ресурса — конвенция (`schema.{id}.fields.{path}`, `ext.{id}.admin`,
 * `acl.policy`), и пока он жил строками, его надо было ПОМНИТЬ в каждом месте
 * сборки: опечатка означала «политика не совпала» — то есть молчаливый deny
 * или, на проверках корня, молчаливый allow. Домены строят свои ресурсы
 * фабриками (SchemaFieldResources и т.п.) и передают этот VO в {@see AccessGate}.
 */
final readonly class ResourceRef implements \Stringable
{
    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new InvalidArgumentException('Access resource cannot be empty.');
        }

        return new self($normalized);
    }

    /** Сборка из сегментов: of('ext', $id, 'admin') -> 'ext.{id}.admin'. */
    public static function of(string ...$segments): self
    {
        return self::fromString(implode('.', array_map(static fn (string $s): string => trim($s), $segments)));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
