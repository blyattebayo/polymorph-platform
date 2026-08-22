<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Menu\Services;

use Polymorph\Platform\Domain\UiConfig\Core\Models\UiConfig;
use Polymorph\Platform\Domain\UiConfig\Infrastructure\UiConfigRepository;

final class MenuConfigService
{
    private const NAMESPACE = 'menu';

    public function __construct(
        private readonly UiConfigRepository $configs,
    ) {}

    public function show(string $key): ?UiConfig
    {
        return $this->configs->find(self::NAMESPACE, $key, null);
    }

    public function update(string $key, int $version, string $document): UiConfig
    {
        return $this->configs->save(self::NAMESPACE, $key, null, $version, $document);
    }

    public function delete(string $key): void
    {
        $this->configs->delete(self::NAMESPACE, $key, null);
    }
}
