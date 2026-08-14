<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Write;

use Polymorph\Platform\Domain\DataPlatform\Fields\BatchDependencyResolver;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldTypeRegistry;
use Polymorph\Platform\Domain\DataPlatform\Outbox\RecordEventStore;
use Polymorph\Platform\Domain\DataPlatform\Projection\ProjectionChangeSetBuilder;
use Polymorph\Platform\Domain\DataPlatform\Projection\ProjectionStore;
use Polymorph\Platform\Domain\DataPlatform\Read\LogicalDocumentReader;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaCatalog;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaDocumentProcessor;
use Polymorph\Platform\Domain\DataPlatform\Serialization\CanonicalJson;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;

/** Shared immutable collaborators for record command buses with different authorities. */
final readonly class RecordCommandRuntime
{
    public function __construct(
        public SchemaCatalog $schemas,
        public FieldTypeRegistry $types,
        public SchemaDocumentProcessor $documents,
        public BatchDependencyResolver $dependencies,
        public ProjectionChangeSetBuilder $projectionChanges,
        public ProjectionStore $projections,
        public LogicalDocumentReader $logicalDocuments,
        public DatabaseJson $json,
        public CanonicalJson $canonicalJson,
        public CommandIdempotencyStore $idempotency,
        public RecordEventStore $recordEvents,
    ) {}
}
