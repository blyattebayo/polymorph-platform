<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Contracts;

use DateTimeImmutable;
use Polymorph\Platform\Domain\Auth\Application\Models\AuthenticatedSession;
use Polymorph\Platform\Domain\Auth\Domain\Session;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\SessionId;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\TokenHash;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;

interface SessionRepository
{
    public function add(Session $session): void;

    public function findAuthenticated(TokenHash $credentialHash, DateTimeImmutable $now): ?AuthenticatedSession;

    public function findForUpdate(SessionId $id): ?Session;

    /** @return list<Session> */
    public function activeForUserForUpdate(UserId $userId, DateTimeImmutable $now): array;

    /** @return list<Session> */
    public function activeForUser(UserId $userId, DateTimeImmutable $now): array;

    public function delete(SessionId $id): void;

    public function deleteForUser(UserId $userId): void;
}
