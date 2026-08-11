<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken;

use Polymorph\Platform\SharedKernel\Access\AccessCheck;
use Polymorph\Platform\SharedKernel\Access\CredentialScopes;
use Polymorph\Platform\SharedKernel\Access\ResourceRef;

final readonly class PersonalAccessTokenScopes
{
    /** @param non-empty-list<AccessCheck> $entries */
    private function __construct(private array $entries) {}

    /**
     * Persistence and HTTP use one canonical representation.
     *
     * @param  list<mixed>  $entries
     */
    public static function fromArray(array $entries): self
    {
        if ($entries === []) {
            throw self::invalid('A personal access token requires at least one scope.');
        }

        $canonical = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                throw self::invalid('Every personal access token scope must be an object.');
            }

            $resource = is_string($entry['resource'] ?? null) ? trim($entry['resource']) : '';
            $action = is_string($entry['action'] ?? null) ? trim($entry['action']) : '';

            if ($resource === '' || $action === '') {
                throw self::invalid('Every personal access token scope requires a resource and action.');
            }

            $key = $resource."\0".$action;
            if (array_key_exists($key, $canonical)) {
                throw self::invalid("Duplicate personal access token scope: {$resource}/{$action}.");
            }

            $canonical[$key] = new AccessCheck(ResourceRef::fromString($resource), $action);
        }

        ksort($canonical, SORT_STRING);

        /** @var non-empty-list<AccessCheck> $normalized */
        $normalized = array_values($canonical);

        return new self($normalized);
    }

    /** @param list<AccessCheck> $entries */
    public static function fromAccessChecks(array $entries): self
    {
        return self::fromArray(array_map(
            static fn (AccessCheck $entry): array => [
                'resource' => (string) $entry->resource,
                'action' => $entry->action,
            ],
            $entries,
        ));
    }

    /** @return non-empty-list<AccessCheck> */
    public function entries(): array
    {
        return $this->entries;
    }

    /** @return non-empty-list<array{resource: string, action: string}> */
    public function toArray(): array
    {
        return array_map(
            static fn (AccessCheck $entry): array => [
                'resource' => (string) $entry->resource,
                'action' => $entry->action,
            ],
            $this->entries,
        );
    }

    public function toCredentialScopes(): CredentialScopes
    {
        return CredentialScopes::of($this->entries);
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }

    private static function invalid(string $message): PersonalAccessTokenInvariantViolation
    {
        return new PersonalAccessTokenInvariantViolation(
            PersonalAccessTokenInvariant::InvalidScopes,
            $message,
        );
    }
}
