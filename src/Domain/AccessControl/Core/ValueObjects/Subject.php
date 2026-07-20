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
        return self::typed(self::TYPE_ROLE, $code);
    }

    public static function typed(string $type, string $identifier): self
    {
        $normalizedType = strtolower(trim($type));
        $normalizedIdentifier = trim($identifier);

        if (! ValidationConstraints::slug()->matches($normalizedType)) {
            throw new InvalidArgumentException('Subject type must be a lowercase identifier.');
        }

        if ($normalizedIdentifier === '') {
            throw new InvalidArgumentException('Subject identifier must not be empty.');
        }

        if (str_contains($normalizedIdentifier, ':')) {
            throw new InvalidArgumentException('Subject identifier must not contain ":".');
        }

        if ($normalizedType === self::TYPE_USER) {
            if (filter_var($normalizedIdentifier, FILTER_VALIDATE_INT) === false || (int) $normalizedIdentifier <= 0) {
                throw new InvalidArgumentException('User id must be positive.');
            }

            $normalizedIdentifier = (string) (int) $normalizedIdentifier;
        }

        if ($normalizedType === self::TYPE_ROLE && ! ValidationConstraints::roleCode()->matches($normalizedIdentifier)) {
            throw new InvalidArgumentException('Role code is invalid.');
        }

        return new self($normalizedType, $normalizedIdentifier);
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

        return self::typed($parts[0], $parts[1]);
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
