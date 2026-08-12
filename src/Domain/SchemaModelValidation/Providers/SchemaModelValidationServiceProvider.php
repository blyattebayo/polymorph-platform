<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\SchemaModel\Events\SchemaChanged;
use Polymorph\Platform\Domain\SchemaModelValidation\Cache\RuleSetCache;
use Polymorph\Platform\Domain\SchemaModelValidation\Compilers\FieldComparisonRuleCompiler;
use Polymorph\Platform\Domain\SchemaModelValidation\Compilers\InCollectionRuleCompiler;
use Polymorph\Platform\Domain\SchemaModelValidation\Compilers\QuantifierRuleCompiler;
use Polymorph\Platform\Domain\SchemaModelValidation\Compilers\RequiredIfRuleCompiler;
use Polymorph\Platform\Domain\SchemaModelValidation\Compilers\RuleCompilerRegistry;
use Polymorph\Platform\Domain\SchemaModelValidation\Compilers\UniqueByRuleCompiler;
use Polymorph\Platform\Domain\SchemaModelValidation\DslValidation\DslValidator;
use Polymorph\Platform\Domain\SchemaModelValidation\FieldPathBuilder;
use Polymorph\Platform\Domain\SchemaModelValidation\Listeners\ForgetRuleSetCacheOnSchemaChange;
use Polymorph\Platform\Domain\SchemaModelValidation\Operands\DslOperandResolver;
use Polymorph\Platform\Domain\SchemaModelValidation\RecordValidationService;
use Polymorph\Platform\Domain\SchemaModelValidation\Schema\SchemaDescriptorProvider;
use Polymorph\Platform\Domain\SchemaModelValidation\Schema\SchemaDescriptorFactory;

final class SchemaModelValidationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RecordValidationService::class);
        $this->app->singleton(SchemaDescriptorProvider::class);
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
        Event::listen(SchemaChanged::class, ForgetRuleSetCacheOnSchemaChange::class);
    }
}
