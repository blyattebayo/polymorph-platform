<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\SdkBridge;

use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use Polymorph\Platform\Support\Logging\LogChannel;
use Polymorph\Sdk\Logging\Logger;

/**
 * Host-адаптер {@see Logger} поверх логгера ядра (канал расширений).
 */
final class SdkLogger implements Logger
{
    private readonly AppLogger $channel;

    public function __construct(private readonly AppLogger $logger)
    {
        $this->channel = $logger->channel(LogChannel::PLUGINS);
    }

    public function info(string $event, array $context = []): void
    {
        $this->channel->info($event, $context);
    }

    public function warning(string $event, array $context = []): void
    {
        $this->channel->warning($event, $context);
    }

    public function error(string $event, array $context = []): void
    {
        $this->channel->error($event, $context);
    }

    public function withContext(array $context): self
    {
        return new self($this->logger->withContext($context));
    }
}
