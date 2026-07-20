<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\UseCases;

use Polymorph\Platform\Domain\Auth\Application\DTO\LoginSessionCommand;
use Polymorph\Platform\Domain\Auth\Application\DTO\LoginSessionResult;
use Polymorph\Platform\Domain\Auth\Application\DTO\RegisterSessionCommand;
use Polymorph\Platform\Domain\Auth\Core\Contracts\EmailVerificationNotifier;
use Polymorph\Platform\Domain\Users\Actions\CreateUserAction;
use Polymorph\Platform\Domain\Users\Core\Models\User;

/**
 * Публичная самостоятельная регистрация: создаёт активного пользователя и сразу
 * открывает сессию (как при логине). Переиспользует CreateUserAction (уникальность
 * email, хеширование, событие UserCreated) и LoginSession (refresh-сессия, куки,
 * UserLoggedIn). Email-верификация пока не выполняется — email_verified_at = null.
 */
final readonly class RegisterSession
{
    public function __construct(
        private CreateUserAction $createUser,
        private LoginSession $loginSession,
        private EmailVerificationNotifier $emailVerificationNotifier,
    ) {}

    public function execute(RegisterSessionCommand $command): LoginSessionResult
    {
        $user = $this->createUser->execute([
            'name' => $command->name ?? '',
            'email' => $command->email,
            'password' => $command->password,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => null,
        ]);

        // Письмо с подписанной ссылкой подтверждения (mailer=log в dev/test).
        $this->emailVerificationNotifier->send($user);

        return $this->loginSession->execute(new LoginSessionCommand(
            email: $command->email,
            password: $command->password,
            ip: $command->ip,
            userAgent: $command->userAgent,
        ));
    }
}
