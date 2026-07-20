<?php

declare(strict_types=1);

namespace Polymorph\Platform\TemplateEngine\Core\Filters;

use Polymorph\Platform\TemplateEngine\Core\Errors\ValidationException;

/**
 * Filter registry with built-in SQL-capable filters for VIEW runtime.
 */
class FilterRegistry
{
    /** @var array<string, FilterDescriptor> */
    private array $filters = [];

    public function __construct()
    {
        $this->registerBuiltinFilters();
    }

    /**
     * Get filter descriptor by name
     */
    public function get(string $name): ?FilterDescriptor
    {
        return $this->filters[$name] ?? null;
    }

    /**
     * Check if filter exists
     */
    public function has(string $name): bool
    {
        return isset($this->filters[$name]);
    }

    /**
     * Register custom filter
     */
    public function register(FilterDescriptor $descriptor): void
    {
        $this->filters[$descriptor->name] = $descriptor;
    }

    /**
     * Compile filter to SQL expression
     *
     * @param  array<int, mixed>  $args
     *
     * @throws ValidationException
     */
    public function compileSql(string $name, string $expr, array $args): string
    {
        $this->validate($name, count($args));

        $filter = $this->get($name);
        if (! $filter instanceof FilterDescriptor || ! $filter->supportsSql || ! is_callable($filter->sqlRenderer)) {
            throw new ValidationException("Filter '{$name}' is not supported by SQL engine");
        }

        return ($filter->sqlRenderer)($expr, $args);
    }

    /**
     * Register all built-in SQL filters.
     */
    private function registerBuiltinFilters(): void
    {
        // upper: Convert to uppercase (scalar only)
        $this->register(new FilterDescriptor(
            name: 'upper',
            vectorized: false,
            supportsSql: true,
            minArgs: 0,
            maxArgs: 0,
            handler: fn ($value) => is_string($value) ? strtoupper($value) : $value,
            sqlRenderer: fn (string $expr, array $args): string => "UPPER(($expr)::text)",
        ));

        // lower: Convert to lowercase (scalar only)
        $this->register(new FilterDescriptor(
            name: 'lower',
            vectorized: false,
            supportsSql: true,
            minArgs: 0,
            maxArgs: 0,
            handler: fn ($value) => is_string($value) ? strtolower($value) : $value,
            sqlRenderer: fn (string $expr, array $args): string => "LOWER(($expr)::text)",
        ));

        // truncate: Truncate string to length (scalar only)
        $this->register(new FilterDescriptor(
            name: 'truncate',
            vectorized: false,
            supportsSql: true,
            minArgs: 1,
            maxArgs: 1,
            handler: fn ($value, int $length) => is_string($value)
                ? mb_substr($value, 0, $length)
                : $value,
            sqlRenderer: function (string $expr, array $args): string {
                if (count($args) !== 1 || ! is_int($args[0])) {
                    throw new ValidationException("Filter 'truncate' expects one integer argument");
                }

                $len = max(0, (int) $args[0]);

                return "LEFT(($expr)::text, {$len})";
            },
        ));

        // default: Return default value if empty (scalar only)
        $this->register(new FilterDescriptor(
            name: 'default',
            vectorized: false,
            supportsSql: true,
            minArgs: 1,
            maxArgs: 1,
            handler: fn ($value, $default) => empty($value) ? $default : $value,
            sqlRenderer: function (string $expr, array $args): string {
                if (count($args) !== 1) {
                    throw new ValidationException("Filter 'default' expects one argument");
                }

                $arg = $args[0];
                $literal = is_int($arg)
                    ? (string) $arg
                    : $this->sqlStringLiteral((string) $arg);

                return "COALESCE(NULLIF(($expr)::text, ''), {$literal})";
            },
        ));
    }

    /**
     * Validate filter call (name exists, arg count correct)
     *
     * @throws ValidationException
     */
    public function validate(string $name, int $argCount): void
    {
        $filter = $this->get($name);

        if (! $filter) {
            throw new ValidationException("Unknown filter: {$name}");
        }

        if ($argCount < $filter->minArgs) {
            throw new ValidationException(
                "Filter '{$name}' requires at least {$filter->minArgs} argument(s), got {$argCount}"
            );
        }

        if ($argCount > $filter->maxArgs) {
            throw new ValidationException(
                "Filter '{$name}' accepts at most {$filter->maxArgs} argument(s), got {$argCount}"
            );
        }
    }

    private function sqlStringLiteral(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}
