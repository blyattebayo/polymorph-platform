<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Logging;

use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use Illuminate\Log\LogManager;
use Psr\Log\LogLevel;
use Stringable;

final class LaravelAppLogger implements AppLogger
{
    /**
     * @param  array<string, mixed>  $baseContext
     */
    public function __construct(
        private readonly LogManager $logs,
        private readonly LogContextEnricher $enricher,
        private readonly LogContextRedactor $redactor,
        private readonly LogChannelResolver $channels,
        private readonly ?string $channel = null,
        private readonly array $baseContext = [],
    ) {}

    public function channel(string $channel): AppLogger
    {
        return new self($this->logs, $this->enricher, $this->redactor, $this->channels, $channel, $this->baseContext);
    }

    public function withContext(array $context): AppLogger
    {
        return new self(
            $this->logs,
            $this->enricher,
            $this->redactor,
            $this->channels,
            $this->channel,
            array_merge($this->baseContext, $context),
        );
    }

    public function event(string $event, array $context = [], ?string $message = null, string $level = LogLevel::INFO): void
    {
        $this->log($level, $message ?? $event, array_merge($context, ['event' => $event]));
    }

    public function emergency(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $message = (string) $message;
        $channel = $this->channels->resolve($this->channel, $message, $context);

        if (! array_key_exists('event', $context) && $this->channels->isRoutableEvent($message, $context)) {
            $context['event'] = $message;
        }

        $context = array_merge($this->baseContext, $context);
        $context = $this->enricher->enrich($context);
        $context = $this->redactor->redact($context);

        $this->logs->channel($channel)->log($level, $message, $context);
    }
}
