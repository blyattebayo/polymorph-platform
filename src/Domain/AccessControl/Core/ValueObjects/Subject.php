<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\ValueObjects;

use InvalidArgumentException;
use Polymorph\Platform\Support\Validation\ValidationConstraints;

final readonly class Subject
{
    private const TYPE_USER = 'user';

    private const TYPE_ROLE = 'role';

    private function __construct(
        private string $type,
        private string $identifier,
    ) {}

    public static function user(int $id): self
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('User id must be positive.');
        }

        return new self(self::TYPE_USER, (string) $id);
    }

    public static function role(string $code): self
    {
        $normalizedIdentifier = trim($code);

        if ($normalizedIdentifier === '') {
            throw new InvalidArgumentException('Role code must not be empty.');
        }

        if (str_contains($normalizedIdentifier, ':')) {
            throw new InvalidArgumentException('Role code must not contain ":".');
        }

        if (! ValidationConstraints::roleCode()->matches($normalizedIdentifier)) {
            throw new InvalidArgumentException('Role code is invalid.');
        }

        return new self(self::TYPE_ROLE, $normalizedIdentifier);
    }

    public static function fromString(string $subject): self
    {
        $normalized = trim($subject);
        if ($normalized === '') {
            throw new InvalidArgumentException('subject is required.');
        }

        $parts = explode(':', $normalized, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new InvalidArgumentException('Invalid subject format. Expected type:identifier.');
        }

        return match ($parts[0]) {
            self::TYPE_USER => self::parseUserIdentifier($parts[1]),
            self::TYPE_ROLE => self::role($parts[1]),
            default => throw new InvalidArgumentException('Subject type must be user or role.'),
        };
    }

    private static function parseUserIdentifier(string $identifier): self
    {
        $normalized = trim($identifier);
        if (filter_var($normalized, FILTER_VALIDATE_INT) === false || (int) $normalized <= 0) {
            throw new InvalidArgumentException('User id must be positive.');
        }

        return self::user((int) $normalized);
    }

    public function type(): string
    {
        return $this->type;
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function equals(self $other): bool
    {
        return (string) $this === (string) $other;
    }

    public function __toString(): string
    {
        return $this->type.':'.$this->identifier;
    }
}
