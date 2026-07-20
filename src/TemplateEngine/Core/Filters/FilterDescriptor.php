<?php

declare(strict_types=1);

namespace Polymorph\Platform\TemplateEngine\Core\Filters;

use Closure;

/**
 * Filter descriptor with metadata
 */
readonly class FilterDescriptor
{
    /**
     * @param string $name Filter name
     * @param bool $vectorized True if filter operates on lists, false for scalar-only
     * @param bool $supportsSql True if filter can be compiled by SQL view engine
     * @param int $minArgs Minimum number of arguments
     * @param int $maxArgs Maximum number of arguments
     * @param callable $handler Filter implementation: fn(mixed $value, ...$args): mixed
     * @param Closure(string, array<int, mixed>): string|null $sqlRenderer SQL renderer: fn(string $expr, array $args): string
     */
    public function __construct(
        public string $name,
        public bool $vectorized,
        public bool $supportsSql,
        public int $minArgs,
        public int $maxArgs,
        public mixed $handler,
        public ?Closure $sqlRenderer = null,
    ) {
    }
}

