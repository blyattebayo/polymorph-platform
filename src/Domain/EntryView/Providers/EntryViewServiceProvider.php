<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\EntryView\Providers;

use Polymorph\Platform\Domain\EntryView\Core\Contracts\EntryViewRepository;
use Polymorph\Platform\Domain\EntryView\Infrastructure\Repositories\EloquentEntryViewRepository;
use Polymorph\Platform\Domain\EntryView\Listeners\LogEntryViewEvent;
use Polymorph\Platform\Domain\EntryView\Queries\FindEntryViewQuery;
use Polymorph\Platform\Domain\EntryView\Services\EntryViewService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider для EntryView Domain.
 *
 * Регистрирует все зависимости, биндинги и observers для EntryView системы.
 * Следует архитектуре Media domain.
 *
 * @package Polymorph\Platform\Domain\EntryView\Providers
 */
class EntryViewServiceProvider extends ServiceProvider
{
    /**
     * Регистрация биндингов в контейнере.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerRepositories();
        $this->registerQueries();

        $this->app->singleton(EntryViewService::class);
    }

    /**
     * Bootstrap сервисов после регистрации всех providers.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerEventListeners();
    }

    /**
     * Регистрация репозиториев.
     *
     * @return void
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
     *
     * @return void
     */
    protected function registerQueries(): void
    {
        $this->app->singleton(FindEntryViewQuery::class);
    }

    /**
     * Регистрация Event Listeners.
     *
     * @return void
     */
    protected function registerEventListeners(): void
    {
        // Регистрация подписчика на события
        Event::subscribe(LogEntryViewEvent::class);
    }
}
