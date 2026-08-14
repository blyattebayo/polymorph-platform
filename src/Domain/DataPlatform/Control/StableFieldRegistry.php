<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Control;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;

/** Owns definition-scoped stable field identities independently of schema versions. */
final class StableFieldRegistry
{
    public function resolve(int $definitionId, FieldSpecification $specification): string
    {
        if ($specification->fieldId !== null) {
            $owned = DB::table('dp_fields')
                ->where('id', $specification->fieldId)
                ->where('record_definition_id', $definitionId)
                ->exists();
            if (! $owned) {
                throw DataPlatformBadRequest::because(
                    'field_does_not_belong_to_definition',
                    "Stable field '{$specification->fieldId}' does not belong to this definition.",
                    ['field_id' => $specification->fieldId, 'record_definition_id' => $definitionId],
                );
            }

            return $specification->fieldId;
        }

        if ($specification->key === '') {
            throw DataPlatformBadRequest::because('missing_stable_field_key', 'A field requires a stable key.');
        }
        $existing = DB::table('dp_fields')
            ->where('record_definition_id', $definitionId)
            ->where('key', $specification->key)
            ->value('id');
        if (is_string($existing)) {
            return $existing;
        }

        $fieldId = (string) Str::ulid();
        DB::table('dp_fields')->insert([
            'id' => $fieldId,
            'record_definition_id' => $definitionId,
            'key' => $specification->key,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $fieldId;
    }
}
