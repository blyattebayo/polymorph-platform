<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Providers;

use Polymorph\Platform\PipelineCore\Locking\DbAdvisoryLockManager;
use Polymorph\Platform\PipelineCore\Locking\LockManager;
use Polymorph\Platform\PipelineCore\Observability\PipelineLogger;
use Polymorph\Platform\PipelineCore\Observability\PipelineTracer;
use Polymorph\Platform\PipelineCore\Observability\ProjectPipelineLogger;
use Polymorph\Platform\PipelineCore\Runtime\PipelineExecutor;
use Polymorph\Platform\PipelineCore\Runtime\TransactionalPipelineRunner;
use Polymorph\Platform\PipelineCore\Tx\TransactionManager;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use Illuminate\Support\ServiceProvider;

class PipelineCoreServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Observability
        $this->app->singleton(PipelineLogger::class, function ($app) {
            return new ProjectPipelineLogger($app->make(AppLogger::class));
        });

        // PipelineTracer — singleton: PipelineExecutor тоже singleton и получает его в конструктор один раз
        $this->app->singleton(PipelineTracer::class, function ($app) {
            return new PipelineTracer($app->make(PipelineLogger::class));
        });

        // Locking
        $this->app->singleton(LockManager::class, function ($app) {
            return new DbAdvisoryLockManager($app->make(PipelineLogger::class));
        });

        // Transaction management — тонкая обёртка над нативным DB, без состояния.
        $this->app->scoped(TransactionManager::class);

        // Runtime
        $this->app->singleton(PipelineExecutor::class, function ($app) {
            return new PipelineExecutor(
                $app->make(LockManager::class),
                $app->make(PipelineLogger::class),
                $app->make(PipelineTracer::class)
            );
        });

        // TransactionalPipelineRunner должен быть scoped, т.к. он захватывает TransactionManager (scoped).
        // Singleton захватил бы TransactionManager реквеста #1 и передавал его всем последующим реквестам.
        $this->app->scoped(TransactionalPipelineRunner::class, function ($app) {
            return new TransactionalPipelineRunner(
                $app->make(PipelineExecutor::class),
                $app->make(TransactionManager::class)
            );
        });

    }

    /**
     * Bootstrap services.
     */
    public function boot(): void {}
}
