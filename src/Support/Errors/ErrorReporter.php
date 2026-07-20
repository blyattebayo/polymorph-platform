<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Errors;

use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use Polymorph\Platform\Support\Logging\LogChannel;

final class ErrorReporter
{
    private function __construct() {}

    public static function report(\Throwable $throwable, ErrorPayload $payload, ?ErrorReportDefinition $definition): void
    {
        $level = $definition?->level ?? 'error';
        $message = $definition?->message ?? $payload->title;

        $context = [
            'event' => 'api.exception',
            'exception' => $throwable,
            'error_code' => $payload->code->value,
            'error_type' => $payload->type,
            'status' => $payload->status,
            'detail' => $payload->detail,
            'meta' => $payload->meta(),
            'trace_id' => $payload->traceId,
        ];

        $additional = $definition?->resolveContext($throwable, $payload) ?? [];

        if ($additional !== []) {
            $context = array_merge($context, $additional);
        }

        $context = array_filter(
            $context,
            static fn ($value) => $value !== null,
        );

        app(AppLogger::class)->channel(LogChannel::API)->log($level, $message, $context);
    }
}
