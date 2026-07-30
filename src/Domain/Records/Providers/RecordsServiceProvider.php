<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\RecordDefinitions\Events\RecordDefinitionForceDeleting;
use Polymorph\Platform\Domain\Records\Access\RecordsCapabilities;
use Polymorph\Platform\Domain\Records\Core\Contracts\RecordRepository;
use Polymorph\Platform\Domain\Records\Infrastructure\Extractors\RecordMediaExtractor;
use Polymorph\Platform\Domain\Records\Infrastructure\Extractors\RecordRefExtractor;
use Polymorph\Platform\Domain\Records\Infrastructure\Repositories\EloquentRecordRepository;
use Polymorph\Platform\Domain\Records\Listeners\CleanupRecordDependenciesOnRecordDefinitionForceDelete;
use Polymorph\Platform\Domain\Records\Query\RecordQueryCriteriaFactory;
use Polymorph\Platform\Domain\Records\Services\RecordDisplayValueService;
use Polymorph\Platform\Domain\Records\Services\RecordIncludedDataAssembler;
use Polymorph\Platform\Domain\Records\Services\RecordRelationshipResolver;
use Polymorph\Platform\Domain\Records\Support\RecordSchemaScalarValueNormalizer;
use Polymorph\Platform\Domain\Records\Support\RecordSchemaValueNormalizer;
use Polymorph\Platform\Support\Json\PathValueResolver;

/**
 * Service Provider для модуля Records.
 *
 * Регистрирует сервисы, контрактные зависимости, observers и policies
 * для работы с записью Record.
 */
class RecordsServiceProvider extends ServiceProvider
{
    /**
     * Регистрация сервисов приложения.
     */
    public function register(): void
    {
        // Регистрация сервисов.
        $this->app->singleton(RecordSchemaScalarValueNormalizer::class);
        $this->app->singleton(RecordSchemaValueNormalizer::class);
        $this->app->singleton(RecordQueryCriteriaFactory::class);
        $this->app->singleton(PathValueResolver::class);
        $this->app->singleton(RecordRepository::class, EloquentRecordRepository::class);
        $this->app->singleton(RecordDisplayValueService::class);
        $this->app->singleton(RecordRefExtractor::class);
        $this->app->singleton(RecordMediaExtractor::class);
        $this->app->singleton(RecordRelationshipResolver::class);
        $this->app->singleton(RecordIncludedDataAssembler::class);
    }

    /**
     * Загрузка сервисов приложения.
     */
    public function boot(): void
    {
        Event::listen(RecordDefinitionForceDeleting::class, CleanupRecordDependenciesOnRecordDefinitionForceDelete::class);

        $this->app->tag([RecordsCapabilities::class], 'access.capability_providers');
    }

    /**
     * Получить сервисы, предоставляемые провайдером.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            RecordRepository::class,
            RecordDisplayValueService::class,
            RecordRelationshipResolver::class,
            RecordIncludedDataAssembler::class,
            PathValueResolver::class,
            RecordRefExtractor::class,
            RecordMediaExtractor::class,
        ];
    }
}
