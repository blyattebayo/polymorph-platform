<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Observability;

use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use Polymorph\Platform\Support\Logging\LogChannel;

final class ProjectPipelineLogger implements PipelineLogger
{
    public function __construct(
        private readonly AppLogger $logger,
    ) {}

    public function info(string $message, array $context = []): void
    {
        $this->logger->channel(LogChannel::PIPELINE)->info($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logger->channel(LogChannel::PIPELINE)->warning($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->logger->channel(LogChannel::PIPELINE)->error($message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->logger->channel(LogChannel::PIPELINE)->debug($message, $context);
    }
}
