<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Control;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformResourceNotFound;
use Polymorph\Platform\Domain\DataPlatform\Projection\DisplayTemplateCompiler;
use Polymorph\Platform\Domain\DataPlatform\Projection\ProjectionRebuildScheduler;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;

/** Updates definition metadata after validating every metadata-owned invariant. */
final class DefinitionMetadataService
{
    public function __construct(
        private readonly DatabaseJson $json,
        private readonly DisplayTemplateCompiler $displayTemplates,
        private readonly ProjectionRebuildScheduler $projectionRebuilds,
    ) {}

    /** @param array<string, mixed> $metadataPatch */
    public function update(int $definitionId, ?string $name, array $metadataPatch): void
    {
        if (array_key_exists('form_configs', $metadataPatch)) {
            throw DataPlatformBadRequest::because(
                'reserved_definition_metadata',
                'Form configuration must be updated through the form-config endpoint.',
                ['keys' => ['form_configs']],
            );
        }

        DB::transaction(function () use ($definitionId, $name, $metadataPatch): void {
            $definition = DB::table('dp_record_definitions')->where('id', $definitionId)->lockForUpdate()->first();
            if ($definition === null) {
                throw DataPlatformResourceNotFound::for('record-definition', $definitionId);
            }

            $metadata = $this->json->decodeMap($definition->metadata, 'dp_record_definitions.metadata');
            $previousTemplate = trim((string) ($metadata['display_template'] ?? ''));
            $metadata = array_replace($metadata, $metadataPatch);
            if (array_key_exists('display_template', $metadataPatch)) {
                $compiled = $this->displayTemplates->compile(
                    $definitionId,
                    is_string($metadata['display_template'] ?? null) ? $metadata['display_template'] : null,
                );
                $metadata['display_template'] = $compiled->source === '' ? null : $compiled->source;
            }

            DB::table('dp_record_definitions')->where('id', $definitionId)->update([
                'name' => $name === null ? $definition->name : trim($name),
                'metadata' => $this->json->encodeNullableMap($metadata),
                'updated_at' => now(),
            ]);

            $currentTemplate = trim((string) ($metadata['display_template'] ?? ''));
            if ($currentTemplate !== $previousTemplate) {
                $this->projectionRebuilds->scheduleDefinition($definitionId);
            }
        });
    }
}
