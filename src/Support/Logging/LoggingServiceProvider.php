<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Logging;

use Illuminate\Log\LogManager;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use Psr\Log\LoggerInterface;

final class LoggingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LogContextEnricher::class);
        $this->app->singleton(LogContextRedactor::class);
        $this->app->singleton(LogChannelResolver::class);

        $this->app->singleton(AppLogger::class, function ($app): AppLogger {
            return new LaravelAppLogger(
                $app->make(LogManager::class),
                $app->make(LogContextEnricher::class),
                $app->make(LogContextRedactor::class),
                $app->make(LogChannelResolver::class),
            );
        });

        $this->app->alias(AppLogger::class, LoggerInterface::class);
    }
}
