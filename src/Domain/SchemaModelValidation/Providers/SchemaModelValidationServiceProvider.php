<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\SchemaModel\Events\FieldAdded;
use Polymorph\Platform\Domain\SchemaModel\Events\FieldDeleted;
use Polymorph\Platform\Domain\SchemaModel\Events\FieldUpdated;
use Polymorph\Platform\Domain\SchemaModel\Events\SchemaCreated;
use Polymorph\Platform\Domain\SchemaModel\Events\SchemaDeleted;
use Polymorph\Platform\Domain\SchemaModel\Events\SchemaUpdated;
use Polymorph\Platform\Domain\SchemaModelValidation\Cache\RuleSetCache;
use Polymorph\Platform\Domain\SchemaModelValidation\Compilers\FieldComparisonRuleCompiler;
use Polymorph\Platform\Domain\SchemaModelValidation\Compilers\InCollectionRuleCompiler;
use Polymorph\Platform\Domain\SchemaModelValidation\Compilers\QuantifierRuleCompiler;
use Polymorph\Platform\Domain\SchemaModelValidation\Compilers\RequiredIfRuleCompiler;
use Polymorph\Platform\Domain\SchemaModelValidation\Compilers\RuleCompilerRegistry;
use Polymorph\Platform\Domain\SchemaModelValidation\Compilers\UniqueByRuleCompiler;
use Polymorph\Platform\Domain\SchemaModelValidation\Contracts\SchemaDescriptorProviderInterface;
use Polymorph\Platform\Domain\SchemaModelValidation\Contracts\SchemaValidationRulesEngineInterface;
use Polymorph\Platform\Domain\SchemaModelValidation\DslValidation\DslValidator;
use Polymorph\Platform\Domain\SchemaModelValidation\FieldPathBuilder;
use Polymorph\Platform\Domain\SchemaModelValidation\Listeners\ForgetRuleSetCacheOnSchemaChange;
use Polymorph\Platform\Domain\SchemaModelValidation\Operands\DslOperandResolver;
use Polymorph\Platform\Domain\SchemaModelValidation\RecordValidationService;
use Polymorph\Platform\Domain\SchemaModelValidation\Schema\EloquentSchemaDescriptorProvider;
use Polymorph\Platform\Domain\SchemaModelValidation\Schema\SchemaDescriptorFactory;

final class SchemaModelValidationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SchemaValidationRulesEngineInterface::class, RecordValidationService::class);
        $this->app->singleton(SchemaDescriptorProviderInterface::class, EloquentSchemaDescriptorProvider::class);

        $this->app->singleton(RuleSetCache::class);
        $this->app->singleton(SchemaDescriptorFactory::class);
        $this->app->singleton(DslOperandResolver::class);
        $this->app->singleton(FieldPathBuilder::class);
        $this->app->singleton(FieldComparisonRuleCompiler::class);
        $this->app->singleton(RequiredIfRuleCompiler::class);
        $this->app->singleton(UniqueByRuleCompiler::class);
        $this->app->singleton(QuantifierRuleCompiler::class);
        $this->app->singleton(InCollectionRuleCompiler::class);
        $this->app->singleton(RuleCompilerRegistry::class);
        $this->app->singleton(DslValidator::class);
    }

    public function boot(): void
    {
        // Сброс кэша скомпилированных правил при изменении схемы/полей.
        foreach ([FieldAdded::class, FieldUpdated::class, FieldDeleted::class, SchemaCreated::class, SchemaUpdated::class, SchemaDeleted::class] as $event) {
            Event::listen($event, ForgetRuleSetCacheOnSchemaChange::class);
        }
    }
}
