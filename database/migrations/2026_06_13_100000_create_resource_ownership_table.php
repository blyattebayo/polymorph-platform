<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('resource_ownership', function (Blueprint $table): void {
            $table->id();
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id');
            $table->string('owner_type');
            $table->string('owner_id')->nullable();
            $table->timestamps();

            $table->unique(['resource_type', 'resource_id'], 'resource_ownership_resource_unique');
            $table->index(['owner_type', 'owner_id'], 'resource_ownership_owner_index');
        });

        $now = now();

        foreach (DB::table('schemas')->select(['id', 'code', 'metadata'])->orderBy('id')->get() as $schema) {
            [$ownerType, $ownerId] = $this->ownerFromSchema($schema);

            DB::table('resource_ownership')->insert([
                'resource_type' => 'schema',
                'resource_id' => (int) $schema->id,
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('record_definitions')->select(['id', 'schema_id'])->orderBy('id')->get() as $definition) {
            $owner = null;
            if ($definition->schema_id !== null) {
                $owner = DB::table('resource_ownership')
                    ->where('resource_type', 'schema')
                    ->where('resource_id', (int) $definition->schema_id)
                    ->first(['owner_type', 'owner_id']);
            }

            DB::table('resource_ownership')->insert([
                'resource_type' => 'record_definition',
                'resource_id' => (int) $definition->id,
                'owner_type' => is_string($owner?->owner_type ?? null) ? (string) $owner->owner_type : 'platform',
                'owner_id' => is_string($owner?->owner_id ?? null) ? (string) $owner->owner_id : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_ownership');
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function ownerFromSchema(object $schema): array
    {
        $metadata = $this->decodeMetadata($schema->metadata ?? null);
        $definitionCode = is_string($metadata['definition_code'] ?? null) ? $metadata['definition_code'] : null;
        if (($metadata['owner'] ?? null) === 'plugin' && $definitionCode !== null) {
            $pluginId = $this->pluginIdFromDefinitionCode($definitionCode);
            if ($pluginId !== null) {
                return ['plugin', $pluginId];
            }
        }

        $schemaCode = is_string($schema->code ?? null) ? $schema->code : '';
        $pluginId = $this->pluginIdFromSchemaCode($schemaCode);
        if ($pluginId !== null) {
            return ['plugin', $pluginId];
        }

        return ['platform', null];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (!is_string($metadata) || trim($metadata) === '') {
            return [];
        }

        $decoded = json_decode($metadata, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function pluginIdFromDefinitionCode(string $definitionCode): ?string
    {
        if (preg_match('/^([a-z][a-z0-9_]*)\.[a-z][a-z0-9_]*$/', $definitionCode, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function pluginIdFromSchemaCode(string $schemaCode): ?string
    {
        if (preg_match('/^plugin\.([a-z][a-z0-9_]*)\.[a-z][a-z0-9_]*$/', $schemaCode, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
};
