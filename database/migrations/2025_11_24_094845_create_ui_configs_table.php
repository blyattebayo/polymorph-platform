<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ui_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('namespace', 64);
            $table->string('key', 191);
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->json('document');
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX uq_ui_configs_global ON ui_configs (namespace, key) WHERE user_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX uq_ui_configs_user ON ui_configs (namespace, key, user_id) WHERE user_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('ui_configs');
    }
};
