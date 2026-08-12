<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fields')) {
            DB::statement('CREATE INDEX IF NOT EXISTS fields_parent_id_index ON fields (parent_id)');
        }

        foreach ([
            'schemas_code_index',
            'fields_schema_id_full_path_index',
            'fields_schema_id_parent_id_index',
            'fields_type_index',
            'fields_is_indexed_index',
            'fields_is_system_index',
            'field_media_constraints_field_id_index',
        ] as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }

        Schema::dropIfExists('pipeline_locks');
    }

    public function down(): void {}
};
