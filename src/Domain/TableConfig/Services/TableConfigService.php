<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\TableConfig\Services;

use Polymorph\Platform\Domain\UiConfig\Core\Models\UiConfig;
use Polymorph\Platform\Domain\UiConfig\Infrastructure\UiConfigRepository;
use Polymorph\Platform\Domain\Users\Core\Models\User;

final class TableConfigService
{
    private const NAMESPACE = 'table';

    public function __construct(
        private readonly UiConfigRepository $configs,
    ) {}

    public function showGlobal(string $key): ?UiConfig
    {
        return $this->configs->find(self::NAMESPACE, $key, null);
    }

    public function updateGlobal(string $key, int $version, string $document): UiConfig
    {
        return $this->configs->save(self::NAMESPACE, $key, null, $version, $document);
    }

    public function deleteGlobal(string $key): void
    {
        $this->configs->delete(self::NAMESPACE, $key, null);
    }

    public function showMine(User $actor, string $key): ?UiConfig
    {
        $userId = (int) $actor->getKey();

        return $this->configs->find(self::NAMESPACE, $key, $userId);
    }

    public function updateMine(User $actor, string $key, int $version, string $document): UiConfig
    {
        $userId = (int) $actor->getKey();

        return $this->configs->save(self::NAMESPACE, $key, $userId, $version, $document);
    }

    public function deleteMine(User $actor, string $key): void
    {
        $userId = (int) $actor->getKey();
        $this->configs->delete(self::NAMESPACE, $key, $userId);
    }
}
