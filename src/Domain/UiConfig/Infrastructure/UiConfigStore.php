<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Infrastructure;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\UiConfig\Core\Exceptions\UiConfigRevisionMismatchException;
use Polymorph\Platform\Domain\UiConfig\Core\Exceptions\UiConfigVersionDowngradeException;
use Polymorph\Platform\Domain\UiConfig\Core\Models\UiConfig;
use Polymorph\Platform\PipelineCore\Locking\LockManager;

/**
 * Единственный владелец правил записи UI-конфига: сериализация конкурентных
 * записей через advisory-лок, сверка ревизии и запрет понижения версии формата.
 *
 * Хранилище одно на все конфигурации: адрес любой из них — это {@see UiConfigSlot}.
 * Составные ключи (например, пара «определение записи + схема» у макета карточки)
 * склеивает клиент, а ссылочную целостность держит жизненный цикл приложения,
 * а не внешние ключи.
 *
 * Сверка ревизии обязана выполняться под тем же локом, что и запись: иначе между
 * чтением и записью снова возникает окно, и защита от гонки сама становится
 * гонкой. Свежий token берётся из PostgreSQL sequence, чтобы delete/recreate не
 * переиспользовал старую ревизию. Отсюда обе операции живут здесь, а не в
 * контроллере.
 */
final class UiConfigStore
{
    public function __construct(
        private readonly LockManager $locks,
    ) {}

    public function find(UiConfigSlot $slot): ?UiConfig
    {
        return $this->query($slot)->first();
    }

    /**
     * Автор записи приходит отдельно от адреса: у общей конфигурации он означает
     * «кто правил последним» и в адрес не входит, у личной совпадает с владельцем
     * из адреса.
     */
    public function save(
        UiConfigSlot $slot,
        ?int $authorId,
        int $expectedRevision,
        int $version,
        string $configJson,
    ): UiConfig {
        return DB::transaction(function () use ($slot, $authorId, $expectedRevision, $version, $configJson): UiConfig {
            $this->locks->acquireLock($slot->lock());

            $config = $this->query($slot)->firstOrNew();
            $this->requireRevision($config->exists ? $config : null, $expectedRevision);

            if ($config->exists && $config->version > $version) {
                throw new UiConfigVersionDowngradeException($config->version, $version);
            }

            $config->fill([
                ...$slot->identity(),
                'author_id' => $authorId,
                'version' => $version,
                'revision' => $this->nextRevision(),
                'config' => $configJson,
            ]);
            $config->save();

            return $config;
        });
    }

    public function delete(UiConfigSlot $slot, int $expectedRevision): void
    {
        DB::transaction(function () use ($slot, $expectedRevision): void {
            $this->locks->acquireLock($slot->lock());

            $config = $this->query($slot)->first();
            $this->requireRevision($config, $expectedRevision);

            $config?->delete();
        });
    }

    /**
     * Зачистка при удалении владельца ресурса: без сверки ревизии и без лока,
     * потому что вызывается внутри транзакции того, кто этого владельца удаляет.
     * Условная запись здесь неприменима — клиент этой операции не заявлял.
     *
     * @return int количество удалённых конфигураций
     */
    public function deleteByIdentity(UiConfigSlot $slot): int
    {
        return $this->query($slot)->delete();
    }

    /**
     * Отсутствующая ячейка — ревизия 0. Поэтому отдельный случай «создай, только
     * если ещё нет» не нужен: клиент заявляет 0 и получает конфликт, если кто-то
     * успел создать конфиг раньше.
     */
    private function requireRevision(?UiConfig $config, int $expectedRevision): void
    {
        $storedRevision = $config?->revision ?? 0;

        if ($storedRevision !== $expectedRevision) {
            throw new UiConfigRevisionMismatchException($storedRevision, $expectedRevision);
        }
    }

    /**
     * PostgreSQL sequence выдаёт opaque token, который не повторится даже если
     * строку физически удалили и позднее создали заново. Транзакционный rollback
     * может оставить пропуск — для equality-token это не имеет значения.
     */
    private function nextRevision(): int
    {
        return (int) DB::scalar("SELECT nextval('ui_config_revision_seq')");
    }

    /**
     * @return Builder<UiConfig>
     */
    private function query(UiConfigSlot $slot): Builder
    {
        return UiConfig::query()->where($slot->identity());
    }
}
