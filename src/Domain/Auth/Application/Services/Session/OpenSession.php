<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Services\Session;

use Polymorph\Platform\Domain\Auth\Application\Contracts\AuthenticationLock;
use Polymorph\Platform\Domain\Auth\Application\Contracts\Clock;
use Polymorph\Platform\Domain\Auth\Application\Contracts\IdGenerator;
use Polymorph\Platform\Domain\Auth\Application\Contracts\SessionCredentials;
use Polymorph\Platform\Domain\Auth\Application\Contracts\SessionRepository;
use Polymorph\Platform\Domain\Auth\Application\Contracts\TransactionManager;
use Polymorph\Platform\Domain\Auth\Application\Models\IssuedSession;
use Polymorph\Platform\Domain\Auth\Application\SessionPolicy;
use Polymorph\Platform\Domain\Auth\Domain\Session;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\ClientMetadata;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;
use Polymorph\Platform\Domain\Users\Core\Models\User;

final readonly class OpenSession
{
    public function __construct(
        private Clock $clock,
        private IdGenerator $ids,
        private SessionCredentials $credentials,
        private SessionRepository $sessions,
        private TransactionManager $transactions,
        private AuthenticationLock $authenticationLock,
    ) {}

    public function execute(User $user, ClientMetadata $client): IssuedSession
    {
        return $this->transactions->run(function () use ($user, $client): IssuedSession {
            $now = $this->clock->now();
            $userId = new UserId((int) $user->id);
            $this->authenticationLock->forUser($userId);
            $active = $this->sessions->activeForUserForUpdate($userId, $now);

            foreach (array_slice($active, 0, max(0, count($active) - SessionPolicy::MAX_ACTIVE_PER_USER + 1)) as $oldest) {
                $this->sessions->delete($oldest->id());
            }

            $credential = $this->credentials->issue();
            $session = new Session(
                $this->ids->sessionId(),
                $userId,
                $credential->hash,
                $now,
                $now->modify('+'.SessionPolicy::LIFETIME_SECONDS.' seconds'),
                $client,
            );
            $this->sessions->add($session);

            return new IssuedSession($user, $session->id(), $credential->plainText);
        });
    }
}
