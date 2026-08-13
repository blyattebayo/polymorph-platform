<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordConstraints\Support;

final class RecordUniqueIndexName
{
    private const MAX_IDENTIFIER_LENGTH = 63;

    private const HASH_LENGTH = 16;

    public static function forField(int $definitionId, string $fieldPath, ?string $cast): string
    {
        $hash = substr(md5($fieldPath.'|'.($cast ?? '')), 0, self::HASH_LENGTH);
        $base = "uq_recf_{$definitionId}_";
        $slug = strtolower($fieldPath);
        $slug = preg_replace('/[^a-z0-9_]/', '_', $slug) ?? $slug;
        $slug = trim((string) $slug, '_');
        if ($slug === '') {
            $slug = 'field';
        }

        $maxSlugLength = max(1, self::MAX_IDENTIFIER_LENGTH - strlen($base) - 1 - strlen($hash));

        return $base.substr($slug, 0, $maxSlugLength).'_'.$hash;
    }
}
