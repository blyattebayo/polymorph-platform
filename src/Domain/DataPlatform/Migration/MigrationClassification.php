<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Migration;

enum MigrationClassification: string
{
    case MetadataOnly = 'metadata-only';
    case Additive = 'additive';
    case ProjectionRebuild = 'projection-rebuild';
    case LazyDocumentMigration = 'lazy-document-migration';
    case BreakingMigration = 'breaking-migration';
    case ForbiddenWithoutExplicitMigration = 'forbidden-without-explicit-migration';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
