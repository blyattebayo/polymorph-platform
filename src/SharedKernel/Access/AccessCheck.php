<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Access;

/** Одна проверка для {@see AccessGate::allowsEach()}. */
final readonly class AccessCheck
{
    public function __construct(
        public ResourceRef $resource,
        public string $action,
    ) {}
}
