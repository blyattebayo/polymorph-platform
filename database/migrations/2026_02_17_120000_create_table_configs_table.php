<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('table_configs', function (Blueprint $table) {
            $table->id();
            $table->string('table_key', 191);
            $table->string('scope', 16);
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('schema_version');
            $table->json('config_json');
            $table->timestamps();

            $table->index(['table_key', 'scope'], 'idx_table_configs_table_scope');
            $table->index(['table_key', 'scope', 'user_id'], 'idx_table_configs_table_scope_user');
        });

        DB::statement("CREATE UNIQUE INDEX uq_table_configs_base ON table_configs (table_key, scope) WHERE scope = 'base'");
        DB::statement("CREATE UNIQUE INDEX uq_table_configs_user ON table_configs (table_key, scope, user_id) WHERE scope = 'user'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_configs');
    }
};
