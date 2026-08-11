<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\UseCases\Session;

use Polymorph\Platform\Domain\Auth\Application\Contracts\AuthenticationAudit;
use Polymorph\Platform\Domain\Auth\Application\Contracts\PasswordHasher;
use Polymorph\Platform\Domain\Auth\Application\Contracts\TransactionManager;
use Polymorph\Platform\Domain\Auth\Application\Contracts\UserGateway;
use Polymorph\Platform\Domain\Auth\Application\Exceptions\AuthenticationDenied;
use Polymorph\Platform\Domain\Auth\Application\Models\IssuedSession;
use Polymorph\Platform\Domain\Auth\Application\Services\Session\OpenSession;
use Polymorph\Platform\Domain\Auth\Application\Services\Session\RevokeUserSessions;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\ClientMetadata;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;

final readonly class Login
{
    public function __construct(
        private UserGateway $users,
        private PasswordHasher $passwords,
        private OpenSession $openSession,
        private RevokeUserSessions $revokeUserSessions,
        private TransactionManager $transactions,
        private AuthenticationAudit $audit,
    ) {}

    public function execute(string $email, string $password, ClientMetadata $client): IssuedSession
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || ! $this->passwords->verify($password, $user->passwordHash)) {
            throw AuthenticationDenied::invalidCredentials();
        }

        if (! $user->identity->isActiveAccount()) {
            $this->transactions->run(fn () => $this->revokeUserSessions->execute(new UserId($user->id())));

            throw AuthenticationDenied::inactiveAccount();
        }

        $result = $this->openSession->execute($user, $client);
        $this->audit->loggedIn(new UserId($user->id()), $client);

        return $result;
    }
}
