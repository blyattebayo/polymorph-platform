<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\UseCases;

use Polymorph\Platform\Domain\Auth\Application\DTO\PersonalAccessTokenView;
use Polymorph\Platform\Domain\Auth\Core\Contracts\PersonalAccessTokenRepository;

/**
 * Токены одного пользователя. Назывался ListOwn, хотя админский контроллер
 * зовёт его же для чужого userId — «own» задавало границу, которой у него нет:
 * чей список отдавать, решает вызывающий, а право на это — capability маршрута.
 */
final class ListPersonalAccessTokensForUser
{
    public function __construct(
        private readonly PersonalAccessTokenRepository $repository,
    ) {}

    /**
     * @return list<PersonalAccessTokenView>
     */
    public function execute(int $userId): array
    {
        return array_map(
            static fn ($token): PersonalAccessTokenView => PersonalAccessTokenView::fromModel($token),
            $this->repository->listForUser($userId),
        );
    }
}
