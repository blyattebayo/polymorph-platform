<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Canonical storage for the versioned data platform. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dp_record_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->ulid('current_schema_version_id')->nullable();
            $table->timestamps();
        });

        Schema::create('dp_schema_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('record_definition_id')->constrained('dp_record_definitions')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('state', 24)->default('draft');
            $table->ulid('previous_version_id')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('validated_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['record_definition_id', 'version'], 'dp_schema_versions_definition_version_uq');
            $table->index(['record_definition_id', 'state'], 'dp_schema_versions_definition_state_idx');
        });

        Schema::create('dp_fields', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('record_definition_id')->constrained('dp_record_definitions')->cascadeOnDelete();
            $table->string('key');
            $table->timestamps();

            $table->unique(['record_definition_id', 'key'], 'dp_fields_definition_key_uq');
        });

        Schema::create('dp_schema_fields', function (Blueprint $table): void {
            $table->id();
            $table->ulid('schema_version_id');
            $table->ulid('field_id');
            $table->ulid('parent_field_id')->nullable();
            $table->string('path');
            $table->string('name');
            $table->string('type', 40);
            $table->string('cardinality', 8)->default('one');
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('projection_version')->default(1);
            $table->jsonb('constraints')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('schema_version_id')->references('id')->on('dp_schema_versions')->cascadeOnDelete();
            $table->foreign('field_id')->references('id')->on('dp_fields')->cascadeOnDelete();
            $table->foreign('parent_field_id')->references('id')->on('dp_fields')->restrictOnDelete();
            $table->unique(['schema_version_id', 'field_id'], 'dp_schema_fields_version_field_uq');
            $table->unique(['schema_version_id', 'path'], 'dp_schema_fields_version_path_uq');
            $table->index(['field_id', 'schema_version_id'], 'dp_schema_fields_field_version_idx');
        });

        Schema::create('dp_projection_definitions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('schema_version_id');
            $table->ulid('field_id')->nullable();
            $table->string('kind', 32);
            $table->unsignedInteger('version')->default(1);
            $table->string('state', 20)->default('pending');
            $table->jsonb('config')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('applied_at')->nullable();
            $table->timestamps();

            $table->foreign('schema_version_id')->references('id')->on('dp_schema_versions')->cascadeOnDelete();
            $table->foreign('field_id')->references('id')->on('dp_fields')->cascadeOnDelete();
            $table->unique(['schema_version_id', 'field_id', 'kind'], 'dp_projection_definitions_identity_uq');
        });

        Schema::create('dp_schema_migration_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('record_definition_id')->constrained('dp_record_definitions')->cascadeOnDelete();
            $table->ulid('from_schema_version_id');
            $table->ulid('to_schema_version_id');
            $table->string('classification', 40);
            $table->string('state', 24)->default('draft');
            $table->jsonb('operations');
            $table->jsonb('checkpoint')->nullable();
            $table->jsonb('invalid_records')->nullable();
            $table->unsignedBigInteger('processed_count')->default(0);
            $table->unsignedBigInteger('failed_count')->default(0);
            $table->timestamps();

            $table->foreign('from_schema_version_id')->references('id')->on('dp_schema_versions')->restrictOnDelete();
            $table->foreign('to_schema_version_id')->references('id')->on('dp_schema_versions')->restrictOnDelete();
            $table->unique(['from_schema_version_id', 'to_schema_version_id'], 'dp_schema_migration_chain_uq');
            $table->index(['state', 'updated_at'], 'dp_schema_migration_progress_idx');
        });

        Schema::create('dp_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('record_definition_id')->constrained('dp_record_definitions')->restrictOnDelete();
            $table->ulid('schema_version_id');
            $table->jsonb('data');
            $table->unsignedBigInteger('revision')->default(1);
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletesTz();

            $table->foreign('schema_version_id')->references('id')->on('dp_schema_versions')->restrictOnDelete();
            $table->index(['record_definition_id', 'deleted_at', 'id'], 'dp_records_definition_page_idx');
            $table->index(['record_definition_id', 'revision'], 'dp_records_definition_revision_idx');
            $table->index(['schema_version_id', 'id'], 'dp_records_schema_backfill_idx');
        });

        Schema::create('dp_media_processing_states', function (Blueprint $table): void {
            $table->ulid('media_id')->primary();
            $table->string('state', 16)->default('ready');
            $table->timestampsTz();

            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
            $table->index(['state', 'updated_at'], 'dp_media_processing_state_idx');
        });

        Schema::create('dp_ref_edges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_record_id')->constrained('dp_records')->cascadeOnDelete();
            $table->ulid('field_id');
            $table->string('occurrence', 512)->default('$');
            $table->ulid('item_id')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedBigInteger('target_record_id');
            $table->string('deletion_policy', 24)->default('restrict');
            $table->unsignedInteger('projection_version')->default(1);
            $table->timestamps();

            $table->foreign('field_id')->references('id')->on('dp_fields')->restrictOnDelete();
            $table->foreign('target_record_id')->references('id')->on('dp_records')->restrictOnDelete();
            $table->unique(['source_record_id', 'field_id', 'occurrence', 'position'], 'dp_ref_edges_occurrence_uq');
            $table->index(['target_record_id', 'field_id', 'source_record_id'], 'dp_ref_edges_reverse_idx');
            $table->index(['source_record_id', 'field_id', 'position'], 'dp_ref_edges_forward_idx');
        });

        Schema::create('dp_media_edges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_record_id')->constrained('dp_records')->cascadeOnDelete();
            $table->ulid('field_id');
            $table->string('occurrence', 512)->default('$');
            $table->ulid('item_id')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->ulid('media_id');
            $table->jsonb('attachment')->nullable();
            $table->unsignedInteger('projection_version')->default(1);
            $table->timestamps();

            $table->foreign('field_id')->references('id')->on('dp_fields')->restrictOnDelete();
            $table->foreign('media_id')->references('id')->on('media')->restrictOnDelete();
            $table->unique(['source_record_id', 'field_id', 'occurrence', 'position'], 'dp_media_edges_occurrence_uq');
            $table->index(['media_id', 'field_id', 'source_record_id'], 'dp_media_edges_reverse_idx');
            $table->index(['source_record_id', 'field_id', 'position'], 'dp_media_edges_forward_idx');
        });

        Schema::create('dp_unique_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('record_definition_id')->constrained('dp_record_definitions')->cascadeOnDelete();
            $table->foreignId('record_id')->constrained('dp_records')->cascadeOnDelete();
            $table->ulid('field_id');
            $table->char('value_hash', 64);
            $table->jsonb('value');
            $table->unsignedInteger('projection_version')->default(1);
            $table->timestamps();

            $table->foreign('field_id')->references('id')->on('dp_fields')->restrictOnDelete();
            $table->unique(['record_definition_id', 'field_id', 'value_hash'], 'dp_unique_values_value_uq');
            $table->unique(['record_id', 'field_id', 'value_hash'], 'dp_unique_values_record_field_value_uq');
        });

        Schema::create('dp_search_documents', function (Blueprint $table): void {
            $table->foreignId('record_id')->primary()->constrained('dp_records')->cascadeOnDelete();
            $table->text('content')->default('');
            $table->unsignedInteger('projection_version')->default(1);
            $table->timestamps();
        });

        Schema::create('dp_display_values', function (Blueprint $table): void {
            $table->foreignId('record_id')->primary()->constrained('dp_records')->cascadeOnDelete();
            $table->text('value');
            $table->unsignedInteger('projection_version')->default(1);
            $table->timestamps();
            $table->index('value', 'dp_display_values_value_idx');
        });

        Schema::create('dp_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('actor_scope', 80);
            $table->string('command', 80);
            $table->char('key_hash', 64);
            $table->char('request_hash', 64);
            $table->string('state', 20)->default('processing');
            $table->jsonb('response')->nullable();
            $table->timestamps();

            $table->unique(['actor_scope', 'command', 'key_hash'], 'dp_idempotency_scope_uq');
            $table->index(['state', 'updated_at'], 'dp_idempotency_stale_idx');
        });

        Schema::create('dp_audit_log', function (Blueprint $table): void {
            $table->bigIncrements('sequence');
            $table->uuid('operation_id');
            $table->string('command', 80);
            $table->foreignId('record_id')->nullable()->constrained('dp_records')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('revision')->nullable();
            $table->jsonb('changed_field_ids')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique('operation_id');
            $table->index(['record_id', 'sequence'], 'dp_audit_record_sequence_idx');
        });

        Schema::create('dp_outbox', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->uuid('operation_id');
            $table->string('aggregate_type', 64);
            $table->string('aggregate_id', 64);
            $table->string('event_type', 160);
            $table->jsonb('payload');
            $table->jsonb('headers')->nullable();
            $table->timestampTz('available_at')->useCurrent();
            $table->timestampTz('locked_at')->nullable();
            $table->string('locked_by')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['delivered_at', 'available_at', 'id'], 'dp_outbox_delivery_idx');
            $table->index(['aggregate_type', 'aggregate_id', 'created_at'], 'dp_outbox_aggregate_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX dp_records_data_gin_idx ON dp_records USING gin (data jsonb_path_ops)');
            DB::statement("ALTER TABLE dp_search_documents ADD COLUMN document tsvector GENERATED ALWAYS AS (to_tsvector('simple', coalesce(content, ''))) STORED");
            DB::statement('CREATE INDEX dp_search_documents_document_gin_idx ON dp_search_documents USING gin (document)');
            DB::statement("ALTER TABLE dp_schema_versions ADD CONSTRAINT dp_schema_versions_state_ck CHECK (state IN ('draft','validating','backfilling','published','archived'))");
            DB::statement("ALTER TABLE dp_projection_definitions ADD CONSTRAINT dp_projection_definitions_state_ck CHECK (state IN ('pending','applying','applied','failed'))");
            DB::statement("ALTER TABLE dp_media_processing_states ADD CONSTRAINT dp_media_processing_state_ck CHECK (state IN ('uploading','processing','ready','failed'))");
            DB::statement('ALTER TABLE dp_schema_versions ADD CONSTRAINT dp_schema_versions_definition_identity_uq UNIQUE (id, record_definition_id)');
            DB::statement('ALTER TABLE dp_schema_versions ADD CONSTRAINT dp_schema_versions_previous_fk FOREIGN KEY (previous_version_id) REFERENCES dp_schema_versions (id) ON DELETE SET NULL');
            DB::statement('ALTER TABLE dp_record_definitions ADD CONSTRAINT dp_record_definitions_current_schema_fk FOREIGN KEY (current_schema_version_id) REFERENCES dp_schema_versions (id)');
            DB::statement('ALTER TABLE dp_records ADD CONSTRAINT dp_records_schema_definition_fk FOREIGN KEY (schema_version_id, record_definition_id) REFERENCES dp_schema_versions (id, record_definition_id)');
            DB::statement("ALTER TABLE dp_ref_edges ADD CONSTRAINT dp_ref_edges_deletion_policy_ck CHECK (deletion_policy IN ('restrict','nullify','preserve_tombstone','cascade'))");
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION dp_guard_schema_field_mutation()
                RETURNS trigger AS $$
                DECLARE
                    candidate_version char(26);
                    candidate_state varchar(24);
                BEGIN
                    candidate_version := CASE WHEN TG_OP = 'DELETE' THEN OLD.schema_version_id ELSE NEW.schema_version_id END;
                    SELECT state INTO candidate_state FROM dp_schema_versions WHERE id = candidate_version;
                    IF candidate_state IS DISTINCT FROM 'draft' THEN
                        RAISE EXCEPTION 'schema version % is immutable in state %', candidate_version, candidate_state
                            USING ERRCODE = '55000';
                    END IF;
                    RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER dp_schema_fields_immutable_guard
                BEFORE INSERT OR UPDATE OR DELETE ON dp_schema_fields
                FOR EACH ROW EXECUTE FUNCTION dp_guard_schema_field_mutation();
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS dp_guard_schema_field_mutation() CASCADE');
            DB::statement('ALTER TABLE dp_record_definitions DROP CONSTRAINT IF EXISTS dp_record_definitions_current_schema_fk');
        }

        foreach ([
            'dp_outbox',
            'dp_audit_log',
            'dp_idempotency_keys',
            'dp_display_values',
            'dp_search_documents',
            'dp_unique_values',
            'dp_media_edges',
            'dp_ref_edges',
            'dp_media_processing_states',
            'dp_records',
            'dp_schema_migration_plans',
            'dp_projection_definitions',
            'dp_schema_fields',
            'dp_fields',
            'dp_schema_versions',
            'dp_record_definitions',
        ] as $table) {
            Schema::dropIfExists($table);
        }

    }
};
