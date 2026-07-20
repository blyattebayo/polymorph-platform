<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Providers;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts\RecordDefinitionRepository;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts\RecordDefinitionDependencyChecker;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\RecordDefinitions\Infrastructure\Repositories\EloquentRecordDefinitionDependencyChecker;
use Polymorph\Platform\Domain\RecordDefinitions\Infrastructure\Repositories\EloquentRecordDefinitionRepository;
use Polymorph\Platform\Domain\RecordDefinitions\Observers\RecordDefinitionObserver;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider для RecordDefinitions Domain.
 * 
 * Регистрирует зависимости и привязки для Domain RecordDefinitions.
 */
class RecordDefinitionServiceProvider extends ServiceProvider
{
    /**
     * Регистрация сервисов.
     */
    public function register(): void
    {
        // Привязываем интерфейс репозитория к реализации
        $this->app->bind(
            RecordDefinitionRepository::class,
            EloquentRecordDefinitionRepository::class
        );

        $this->app->bind(
            RecordDefinitionDependencyChecker::class,
            EloquentRecordDefinitionDependencyChecker::class,
        );
    }

    /**
     * Загрузка сервисов приложения.
     */
    public function boot(): void
    {
        // Регистрируем Observer
        RecordDefinition::observe(RecordDefinitionObserver::class);
    }
}
