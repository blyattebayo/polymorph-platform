<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Queries;

use Polymorph\Platform\Domain\Users\Core\Contracts\UserRepository;
use Polymorph\Platform\Domain\Users\Core\Models\User;

final readonly class FindUserByEmailQuery
{
    public function __construct(
        private UserRepository $repository,
    ) {
    }

    public function execute(string $email): ?User
    {
        return $this->repository->findByEmail($email);
    }
}
