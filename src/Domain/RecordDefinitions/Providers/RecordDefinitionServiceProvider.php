<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Providers;

use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts\RecordDefinitionDependencyChecker;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts\RecordDefinitionRepository;
use Polymorph\Platform\Domain\RecordDefinitions\Infrastructure\Repositories\EloquentRecordDefinitionDependencyChecker;
use Polymorph\Platform\Domain\RecordDefinitions\Infrastructure\Repositories\EloquentRecordDefinitionRepository;

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
        $this->app->singleton(
            RecordDefinitionRepository::class,
            EloquentRecordDefinitionRepository::class
        );

        $this->app->singleton(
            RecordDefinitionDependencyChecker::class,
            EloquentRecordDefinitionDependencyChecker::class,
        );
    }

}
