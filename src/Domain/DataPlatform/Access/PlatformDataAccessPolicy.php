<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Access;

use Illuminate\Database\Query\Builder;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\SharedKernel\Access\AccessCheck;
use Polymorph\Platform\SharedKernel\Access\AccessGate;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;
use Polymorph\Platform\SharedKernel\Access\ResourceRef;

/** Adapts the canonical ACL engine without exposing platform storage to plugins. */
final class PlatformDataAccessPolicy implements DataAccessPolicy
{
    /** @var array<int, User|null> */
    private array $users = [];

    /** @var array<string,bool> */
    private array $decisions = [];

    public function __construct(private readonly AccessGate $gate) {}

    public function canReadDefinition(?int $actorId, int $definitionId): bool
    {
        return $this->allows($actorId, ResourceRef::of('records', 'definitions', (string) $definitionId), CapabilityCatalog::ACTION_READ);
    }

    public function canWriteDefinition(?int $actorId, int $definitionId): bool
    {
        return $this->allows($actorId, ResourceRef::of('records', 'definitions', (string) $definitionId), CapabilityCatalog::ACTION_WRITE);
    }

    public function canDeleteRecord(?int $actorId, int $definitionId, int $recordId): bool
    {
        return $this->allows($actorId, ResourceRef::of('records', 'definitions', (string) $definitionId, 'records', (string) $recordId), CapabilityCatalog::ACTION_DELETE);
    }

    public function canReadField(?int $actorId, int $definitionId, FieldDefinition $field): bool
    {
        return $this->allowsField($actorId, $definitionId, $field, CapabilityCatalog::ACTION_READ);
    }

    public function canWriteField(?int $actorId, int $definitionId, FieldDefinition $field): bool
    {
        return $this->allowsField($actorId, $definitionId, $field, CapabilityCatalog::ACTION_WRITE);
    }

    public function canReadTargetRecord(?int $actorId, int $recordId): bool
    {
        return $this->allows($actorId, ResourceRef::of('records', 'targets', (string) $recordId), CapabilityCatalog::ACTION_READ);
    }

    public function applyReadableRecordScope(Builder $query, ?int $actorId, int $definitionId): bool
    {
        $user = $this->user($actorId);
        if ($user === null) {
            $query->whereRaw('false');

            return true;
        }

        // A collection grant is a complete SQL scope. Policies containing only
        // per-record target grants fall back to bounded batch evaluation.
        return $this->gate->allows(
            $user,
            ResourceRef::of('records', 'targets'),
            CapabilityCatalog::ACTION_READ,
        );
    }

    public function readableTargetRecordIds(?int $actorId, array $recordIds): array
    {
        $recordIds = array_values(array_unique(array_filter(array_map('intval', $recordIds), static fn (int $id): bool => $id > 0)));

        return array_map('intval', $this->allowedIds(
            $actorId,
            $recordIds,
            static fn (int $id): ResourceRef => ResourceRef::of('records', 'targets', (string) $id),
            CapabilityCatalog::ACTION_READ,
        ));
    }

    public function readableMediaIds(?int $actorId, array $mediaIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $mediaIds))));

        return array_map('strval', $this->allowedIds(
            $actorId,
            $ids,
            static fn (string $id): ResourceRef => ResourceRef::of('media', $id),
            CapabilityCatalog::ACTION_READ,
        ));
    }

    public function attachableRecordIds(?int $actorId, int $sourceDefinitionId, FieldDefinition $field, array $recordIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $recordIds), static fn (int $id): bool => $id > 0)));

        return array_map('intval', $this->allowedIds(
            $actorId,
            $ids,
            static fn (int $id): ResourceRef => ResourceRef::of(
                'records', 'definitions', (string) $sourceDefinitionId, 'fields', $field->id, 'attach', (string) $id,
            ),
            CapabilityCatalog::ACTION_WRITE,
        ));
    }

    public function attachableMediaIds(?int $actorId, int $sourceDefinitionId, FieldDefinition $field, array $mediaIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $mediaIds))));

        return array_map('strval', $this->allowedIds(
            $actorId,
            $ids,
            static fn (string $id): ResourceRef => ResourceRef::of(
                'records', 'definitions', (string) $sourceDefinitionId, 'fields', $field->id, 'attach-media', $id,
            ),
            CapabilityCatalog::ACTION_WRITE,
        ));
    }

    private function fieldResource(int $definitionId, FieldDefinition $field): ResourceRef
    {
        return ResourceRef::of('records', 'definitions', (string) $definitionId, 'fields', $field->id);
    }

    private function allowsField(?int $actorId, int $definitionId, FieldDefinition $field, string $action): bool
    {
        return $this->allows($actorId, $this->fieldResource($definitionId, $field), $action);
    }

    private function allows(?int $actorId, ResourceRef $resource, string $action): bool
    {
        $user = $this->user($actorId);
        if ($user === null) {
            return false;
        }
        $key = $user->id.':'.$action.':'.$resource->value;

        return $this->decisions[$key] ??= $this->gate->allows($user, $resource, $action);
    }

    private function user(?int $actorId): ?User
    {
        if ($actorId === null || $actorId <= 0) {
            return null;
        }

        return $this->users[$actorId] ??= User::query()->find($actorId);
    }

    /** @template T of int|string @param list<T> $ids @param callable(T):ResourceRef $resource @return list<T> */
    private function allowedIds(?int $actorId, array $ids, callable $resource, string $action): array
    {
        $user = $this->user($actorId);
        if ($user === null || $ids === []) {
            return [];
        }
        $checks = array_map(static fn (int|string $id): AccessCheck => new AccessCheck($resource($id), $action), $ids);
        $allowed = $this->gate->allowsEach($user, $checks);
        foreach ($checks as $index => $check) {
            $this->decisions[$user->id.':'.$action.':'.$check->resource->value] = (bool) ($allowed[$index] ?? false);
        }

        return array_values(array_filter(
            $ids,
            static fn (int|string $id, int $index): bool => (bool) ($allowed[$index] ?? false),
            ARRAY_FILTER_USE_BOTH,
        ));
    }
}
