<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Infrastructure;

use Polymorph\Platform\Domain\UiConfig\Core\Models\UiConfig;
use Polymorph\Platform\PipelineCore\Locking\LockKey;

/**
 * Идентичность конфига — namespace и непрозрачный ключ. Всё, что нужно клиенту
 * различать внутри вида, он кодирует в ключ сам: пару «определение записи +
 * схема» у entry view, владельца у персональных настроек. Механика записи живёт
 * в {@see UiConfigStore}.
 */
final class UiConfigRepository
{
    public function __construct(
        private readonly UiConfigStore $store,
    ) {}

    public function find(string $namespace, string $key): ?UiConfig
    {
        return $this->store->find($this->slot($namespace, $key));
    }

    public function save(
        string $namespace,
        string $key,
        int $expectedRevision,
        int $version,
        string $documentJson,
    ): UiConfig {
        return $this->store->save($this->slot($namespace, $key), $expectedRevision, $version, $documentJson);
    }

    public function delete(string $namespace, string $key, int $expectedRevision): void
    {
        $this->store->delete($this->slot($namespace, $key), $expectedRevision);
    }

    /**
     * Зачистка при удалении владельца ресурса: без сверки ревизии и без лока,
     * потому что вызывается внутри транзакции того, кто этого владельца удаляет.
     * Условная запись (см. {@see UiConfigStore}) здесь неприменима — клиент этой
     * операции не заявлял.
     *
     * @return int количество удалённых конфигов
     */
    public function purge(string $namespace, string $keyPattern): int
    {
        return UiConfig::query()
            ->where('namespace', $namespace)
            ->where('key', 'like', $keyPattern)
            ->delete();
    }

    private function slot(string $namespace, string $key): UiConfigSlot
    {
        return new UiConfigSlot(
            ['namespace' => $namespace, 'key' => $key],
            new LockKey('ui-config', $namespace, $key),
        );
    }
}
