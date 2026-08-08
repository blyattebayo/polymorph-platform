<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\UseCases;

use Illuminate\Support\Facades\Event;
use Polymorph\Platform\Domain\Auth\Application\DTO\CreatedPersonalAccessTokenResult;
use Polymorph\Platform\Domain\Auth\Application\DTO\CreatePersonalAccessTokenCommand;
use Polymorph\Platform\Domain\Auth\Application\Policies\TokenManagementPolicy;
use Polymorph\Platform\Domain\Auth\Core\Exceptions\PersonalAccessTokenCreationDisabledException;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\PatConfig;
use Polymorph\Platform\Domain\Auth\Events\PersonalAccessTokenCreated;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\PersonalAccessTokenService;
use Polymorph\Platform\SharedKernel\Identity\AuthenticatedCredential;

/**
 * Выпуск персонального токена — один на оба сценария.
 *
 * Были два use-case'а, CreateOwn и AdminCreate, различавшиеся тем, что второй
 * дополнительно принимал модель целевого пользователя, чтобы взять из неё тот
 * же userId, который вызывающий уже положил в команду. Всё остальное —
 * проверка политики, флаг «выпуск выключен», создание и событие — совпадало
 * строка в строку. Кто кому выписывает токен, решает capability на маршруте.
 */
final class CreatePersonalAccessToken
{
    public function __construct(
        private readonly PersonalAccessTokenService $tokens,
        private readonly TokenManagementPolicy $tokenManagementPolicy,
        private readonly PatConfig $config,
    ) {}

    public function execute(
        CreatePersonalAccessTokenCommand $command,
        ?AuthenticatedCredential $actorCredential,
    ): CreatedPersonalAccessTokenResult {
        $this->tokenManagementPolicy->assertCanManageTokens($actorCredential);

        if (! $this->config->creationEnabled) {
            throw PersonalAccessTokenCreationDisabledException::make();
        }

        $created = $this->tokens->create(
            userId: $command->userId,
            name: $command->name,
            createdByUserId: $command->createdByUserId,
            expiresAt: $this->tokens->resolveExpiresAt($command->ttl),
        );

        Event::dispatch(new PersonalAccessTokenCreated(
            tokenId: (int) $created['token']->id,
            userId: $command->userId,
            createdByUserId: $command->createdByUserId,
        ));

        return new CreatedPersonalAccessTokenResult($created['token'], $created['plaintext']);
    }
}
