<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Ownership;

final class ResourceOwnershipService
{
    public function get(ResourceType $resourceType, int $resourceId): ?ResourceOwner
    {
        $ownership = ResourceOwnership::query()
            ->where('resource_type', $resourceType->value)
            ->where('resource_id', $resourceId)
            ->first();

        if (! $ownership instanceof ResourceOwnership) {
            return null;
        }

        return $ownership->toOwner();
    }

    public function require(ResourceType $resourceType, int $resourceId): ResourceOwner
    {
        return $this->get($resourceType, $resourceId)
            ?? throw MissingResourceOwnershipException::for($resourceType, $resourceId);
    }

    public function fromModel(ResourceOwnership $ownership): ResourceOwner
    {
        return $ownership->toOwner();
    }

    public function set(ResourceType $resourceType, int $resourceId, ResourceOwner $owner): void
    {
        ResourceOwnership::query()->updateOrCreate(
            [
                'resource_type' => $resourceType->value,
                'resource_id' => $resourceId,
            ],
            $owner->toArray(),
        );
    }

    public function copy(
        ResourceType $sourceType,
        int $sourceId,
        ResourceType $targetType,
        int $targetId,
    ): void {
        $this->set($targetType, $targetId, $this->require($sourceType, $sourceId));
    }

    public function ensurePlatformOwner(ResourceType $resourceType, int $resourceId): void
    {
        $exists = ResourceOwnership::query()
            ->where('resource_type', $resourceType->value)
            ->where('resource_id', $resourceId)
            ->exists();

        if (! $exists) {
            $this->set($resourceType, $resourceId, ResourceOwner::platform());
        }
    }

    public function delete(ResourceType $resourceType, int $resourceId): void
    {
        ResourceOwnership::query()
            ->where('resource_type', $resourceType->value)
            ->where('resource_id', $resourceId)
            ->delete();
    }
}
