<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Logging\Contracts;

use Psr\Log\LoggerInterface;

interface AppLogger extends LoggerInterface
{
    public function channel(string $channel): self;

    /**
     * @param  array<string, mixed>  $context
     */
    public function withContext(array $context): self;

    /**
     * @param  array<string, mixed>  $context
     */
    public function event(string $event, array $context = [], ?string $message = null, string $level = 'info'): void;
}
