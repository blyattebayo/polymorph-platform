<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Support;

use Polymorph\Platform\Domain\AccessControl\Services\EffectiveCapabilityResolver;
use Polymorph\Platform\Domain\Users\Core\Models\User;

final class UserCapabilitiesPresenter
{
    /**
     * @var array<int, list<string>>
     */
    private array $cache = [];

    public function __construct(
        private readonly EffectiveCapabilityResolver $capabilities,
    ) {}

    /**
     * @return list<string>
     */
    public function for(User $user): array
    {
        $userId = (int) $user->id;

        return $this->cache[$userId] ??= $this->capabilities->for($user);
    }
}
