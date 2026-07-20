<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Json;

final class PathValueResolver
{
    /**
     * @param array<string, mixed> $payload
     */
    public function get(array $payload, string $path): mixed
    {
        $normalizedPath = trim($path);
        if ($normalizedPath === '') {
            return null;
        }

        return data_get($payload, $normalizedPath);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function getFirstAvailable(array $payload, ?string ...$paths): mixed
    {
        foreach ($paths as $path) {
            if (!is_string($path)) {
                continue;
            }

            $normalizedPath = trim($path);
            if ($normalizedPath === '') {
                continue;
            }

            $value = data_get($payload, $normalizedPath);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }
}
