<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\UseCases;

use Polymorph\Platform\Domain\Auth\Core\Contracts\RefreshSessionRepository;

final readonly class ListOwnAuthSessions
{
    public function __construct(
        private RefreshSessionRepository $sessions,
    ) {}

    /**
     * @param  int|null  $currentSessionId  Значение claim `sid` access-токена запроса.
     *                                      Текущей помечается активная сессия той же семьи:
     *                                      sid мог указывать на уже ротированную строку.
     * @return list<object>
     */
    public function execute(int $userId, ?int $currentSessionId = null): array
    {
        $sessions = $this->sessions->activeForUser($userId);

        $currentFamilyId = $currentSessionId !== null
            ? $this->sessions->familyIdOf($currentSessionId)
            : null;

        foreach ($sessions as $session) {
            $session->current = $currentFamilyId !== null
                && (string) ($session->family_id ?? '') === $currentFamilyId;
        }

        return $sessions;
    }
}
