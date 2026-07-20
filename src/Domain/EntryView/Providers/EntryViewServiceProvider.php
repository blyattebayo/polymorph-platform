<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\EntryView\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\EntryView\Core\Contracts\EntryViewRepository;
use Polymorph\Platform\Domain\EntryView\Infrastructure\Repositories\EloquentEntryViewRepository;
use Polymorph\Platform\Domain\EntryView\Listeners\LogEntryViewEvent;
use Polymorph\Platform\Domain\EntryView\Queries\FindEntryViewQuery;
use Polymorph\Platform\Domain\EntryView\Services\EntryViewService;

/**
 * Service Provider для EntryView Domain.
 *
 * Регистрирует все зависимости, биндинги и observers для EntryView системы.
 * Следует архитектуре Media domain.
 */
class EntryViewServiceProvider extends ServiceProvider
{
    /**
     * Регистрация биндингов в контейнере.
     */
    public function register(): void
    {
        $this->registerRepositories();
        $this->registerQueries();

        $this->app->singleton(EntryViewService::class);
    }

    /**
     * Bootstrap сервисов после регистрации всех providers.
     */
    public function boot(): void
    {
        $this->registerEventListeners();
    }

    /**
     * Регистрация репозиториев.
     */
    protected function registerRepositories(): void
    {
        $this->app->singleton(
            EntryViewRepository::class,
            EloquentEntryViewRepository::class
        );
    }

    /**
     * Регистрация Queries (read операции).
     *
     * Queries инкапсулируют бизнес-логику чтения данных.
     */
    protected function registerQueries(): void
    {
        $this->app->singleton(FindEntryViewQuery::class);
    }

    /**
     * Регистрация Event Listeners.
     */
    protected function registerEventListeners(): void
    {
        // Регистрация подписчика на события
        Event::subscribe(LogEntryViewEvent::class);
    }
}
