<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Logging;

final class LogChannelResolver
{
    /**
     * @var array<string, string>
     */
    private const PREFIX_CHANNELS = [
        'api' => LogChannel::API,
        'auth' => LogChannel::AUTH,
        'entry_view' => LogChannel::SCHEMA,
        'materialization' => LogChannel::SCHEMA,
        'media' => LogChannel::MEDIA,
        'pipeline' => LogChannel::PIPELINE,
        'plugin' => LogChannel::PLUGINS,
        'routing' => LogChannel::ROUTING,
        'schema' => LogChannel::SCHEMA,
        'users' => LogChannel::AUTH,
    ];

    /**
     * @param  array<string, mixed>  $context
     */
    public function resolve(?string $explicitChannel, string $message, array $context = []): string
    {
        if ($explicitChannel !== null) {
            return $explicitChannel;
        }

        return $this->resolveFromMessage($this->eventName($message, $context)) ?? LogChannel::APP;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function isRoutableEvent(string $message, array $context = []): bool
    {
        return $this->resolveFromMessage($this->eventName($message, $context)) !== null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function eventName(string $message, array $context): string
    {
        return is_string($context['event'] ?? null) ? $context['event'] : $message;
    }

    private function resolveFromMessage(string $message): ?string
    {
        if (! str_contains($message, '.')) {
            return null;
        }

        $prefix = strtolower(strtok($message, '.') ?: '');

        return self::PREFIX_CHANNELS[$prefix] ?? null;
    }
}
