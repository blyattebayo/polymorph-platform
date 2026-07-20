<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Rules\Support;

final class ScopedPathResolver
{
    public static function resolve(string $attribute, string $pathTemplate): string
    {
        $attributeIndexes = self::extractIndexes($attribute);
        $templateSegments = explode('.', $pathTemplate);
        $resolved = [];

        foreach ($templateSegments as $segment) {
            if ($segment !== '*') {
                $resolved[] = $segment;
                continue;
            }

            $resolved[] = array_shift($attributeIndexes) ?? '*';
        }

        return implode('.', $resolved);
    }

    /**
     * @return list<string>
     */
    private static function extractIndexes(string $attribute): array
    {
        $indexes = [];

        foreach (explode('.', $attribute) as $segment) {
            if (ctype_digit($segment)) {
                $indexes[] = $segment;
            }
        }

        return $indexes;
    }
}
