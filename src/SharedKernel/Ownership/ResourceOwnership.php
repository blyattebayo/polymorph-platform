<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Ownership;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $resource_type
 * @property int $resource_id
 * @property string $owner_type
 * @property string|null $owner_id
 */
final class ResourceOwnership extends Model
{
    protected $table = 'resource_ownership';

    protected $fillable = [
        'resource_type',
        'resource_id',
        'owner_type',
        'owner_id',
    ];

    public function toOwner(): ResourceOwner
    {
        $ownerType = OwnerType::from((string) $this->owner_type);
        if ($ownerType === OwnerType::PLUGIN) {
            return ResourceOwner::plugin((string) $this->owner_id);
        }

        return $ownerType === OwnerType::SYSTEM
            ? ResourceOwner::system()
            : ResourceOwner::platform();
    }
}
