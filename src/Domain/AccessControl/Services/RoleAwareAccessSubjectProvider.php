<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessSubjectProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;
use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleAssignmentRepository;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;
use InvalidArgumentException;

final class RoleAwareAccessSubjectProvider implements AccessSubjectProvider
{
    /**
     * @var array<int, list<Subject>>
     */
    private array $cache = [];

    public function __construct(
        private readonly RoleAssignmentRepository $roleAssignments,
    ) {
    }

    /**
     * @return list<Subject>
     */
    public function for(UserIdentity $user): array
    {
        $userId = $user->userId();
        if ($userId <= 0) {
            throw new InvalidArgumentException('User id must be positive.');
        }

        return $this->cache[$userId] ??= $this->resolveForUser($userId);
    }

    /**
     * @return list<Subject>
     */
    private function resolveForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $userSubject = Subject::user($userId);
        $subjects = [(string) $userSubject => $userSubject];

        foreach ($this->roleAssignments->roleCodesForUser($userId) as $roleCode) {
            $subject = Subject::role($roleCode);
            $subjects[(string) $subject] = $subject;
        }

        return array_values($subjects);
    }
}
