<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Services;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\Auth\Application\Authentication\AuthenticationContext;
use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleAssignmentRepository;
use Polymorph\Platform\Domain\UiConfig\Core\Models\UiConfig;
use Polymorph\Platform\Domain\UiConfig\Core\UiConfigDelete;
use Polymorph\Platform\Domain\UiConfig\Core\UiConfigDomain;
use Polymorph\Platform\Domain\UiConfig\Core\UiConfigWrite;
use Polymorph\Platform\Domain\UiConfig\Infrastructure\UiConfigSlot;
use Polymorph\Platform\Domain\UiConfig\Infrastructure\UiConfigStore;
use Polymorph\Platform\Support\Errors\ThrowsErrors;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Единственный владелец жизненного цикла конфигураций интерфейса.
 *
 * Частностей не содержит: вид конфигурации закодирован в ключе, а всё, чем
 * конфигурации отличаются друг от друга, сведено к домену — общая или личная.
 * Операция приходит проверенной командой, поэтому сервис не разбирает сырых полей
 * и не знает, кто эту операцию принёс.
 */
final class UiConfigService
{
    use ThrowsErrors;

    public function __construct(
        private readonly UiConfigStore $configs,
        private readonly RoleAssignmentRepository $roleAssignments,
        private readonly AuthenticationContext $auth,
        private readonly AppLogger $logger,
    ) {}

    /**
     * Личная конфигурация подменяет общую, а не дополняет её: нет личной — отдаём
     * общую. Домен ответа сообщается вызывающему, потому что от него зависит
     * ревизия следующей записи: у ещё не созданной личной строки она нулевая.
     */
    public function load(string $key, UiConfigDomain $domain): ?UiConfig
    {
        if ($domain->isGlobal()) {
            return $this->configs->find(UiConfigSlot::global($key));
        }

        return $this->configs->find(UiConfigSlot::personal($key, $this->authorId()))
            ?? $this->configs->find(UiConfigSlot::global($key));
    }

    public function save(UiConfigWrite $write): UiConfig
    {
        $authorId = $this->requireWriteAccess($write->domain);

        $config = $this->configs->save(
            $this->slot($write->key, $write->domain, $authorId),
            $authorId,
            $write->revision,
            $write->version,
            $write->configJson(),
        );

        $this->audit(
            $write->key,
            $write->domain,
            $config->wasRecentlyCreated ? 'created' : 'updated',
            (int) $config->id,
        );

        return $config;
    }

    public function delete(UiConfigDelete $delete): void
    {
        $authorId = $this->requireWriteAccess($delete->domain);

        $this->configs->delete(
            $this->slot($delete->key, $delete->domain, $authorId),
            $delete->revision,
        );

        $this->audit($delete->key, $delete->domain, 'deleted');
    }

    /** Адрес операции: личная ячейка принадлежит актору, общая — экземпляру. */
    private function slot(string $key, UiConfigDomain $domain, int $authorId): UiConfigSlot
    {
        return $domain->isGlobal()
            ? UiConfigSlot::global($key)
            : UiConfigSlot::personal($key, $authorId);
    }

    /**
     * Личную конфигурацию пишет любой аутентифицированный актор — свою и только
     * свою, потому что автор берётся из сессии. Общая одна на весь экземпляр,
     * поэтому её правит только системный администратор.
     *
     * @return int автор записи: у общей — тот, кто её правил, у личной — владелец
     */
    private function requireWriteAccess(UiConfigDomain $domain): int
    {
        $authorId = $this->authorId();

        if ($domain->isGlobal() && ! $this->isSystemAdmin($authorId)) {
            $this->forbidden('Only a system administrator may write a global configuration.', [
                'domain' => $domain->value,
                'required_role' => BuiltInRoleCatalog::ROLE_SYSTEM_ADMIN,
            ]);
        }

        return $authorId;
    }

    private function authorId(): int
    {
        return (int) $this->auth->requireUser()->getKey();
    }

    private function isSystemAdmin(int $userId): bool
    {
        return in_array(
            BuiltInRoleCatalog::ROLE_SYSTEM_ADMIN,
            $this->roleAssignments->roleCodesForUser($userId),
            true,
        );
    }

    private function audit(string $key, UiConfigDomain $domain, string $event, ?int $configId = null): void
    {
        $this->logger->event('ui_config.'.$event, [
            'config_key' => $key,
            'domain' => $domain->value,
            ...($configId === null ? [] : ['config_id' => $configId]),
        ]);
    }
}
