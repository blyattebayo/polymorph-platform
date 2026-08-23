<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Services;

use Polymorph\Platform\Domain\UiConfig\Core\ConfigNamespace;
use Polymorph\Platform\Domain\UiConfig\Core\Models\UiConfig;
use Polymorph\Platform\Domain\UiConfig\Infrastructure\UiConfigRepository;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Единственный владелец жизненного цикла UI-конфига — любого вида.
 *
 * Ключ приходит от клиента как непрозрачная строка: адресация внутри namespace
 * (например, пара «определение записи + схема» у entry view) — дело клиента, и
 * существование адресуемых сущностей здесь не проверяется. Ссылочную целостность
 * держит удаление владельцев ресурса, см. {@see ConfigCleaner}.
 *
 * Права на запись не спрашиваются: настройка интерфейса открыта любому
 * аутентифицированному актору, а единственное условие — аутентификация — стоит
 * на группе маршрутов.
 */
final class ConfigService
{
    public function __construct(
        private readonly UiConfigRepository $configs,
        private readonly AppLogger $logger,
    ) {}

    public function show(ConfigNamespace $namespace, string $key): ?UiConfig
    {
        return $this->configs->find($namespace->value, $key);
    }

    public function save(
        ConfigNamespace $namespace,
        string $key,
        int $expectedRevision,
        int $version,
        string $document,
    ): UiConfig {
        $config = $this->configs->save(
            $namespace->value,
            $key,
            $expectedRevision,
            $version,
            $document,
        );

        $this->audit($namespace, $key, $config->wasRecentlyCreated ? 'created' : 'updated', (int) $config->id);

        return $config;
    }

    public function delete(ConfigNamespace $namespace, string $key, int $expectedRevision): void
    {
        $this->configs->delete($namespace->value, $key, $expectedRevision);

        $this->audit($namespace, $key, 'deleted');
    }

    private function audit(ConfigNamespace $namespace, string $key, string $event, ?int $configId = null): void
    {
        $this->logger->event('ui_config.'.$event, [
            'namespace' => $namespace->value,
            'config_key' => $key,
            ...($configId === null ? [] : ['config_id' => $configId]),
        ]);
    }
}
