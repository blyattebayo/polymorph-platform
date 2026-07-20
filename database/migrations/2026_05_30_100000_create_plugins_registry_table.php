<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugins_registry', function (Blueprint $table): void {
            $table->id();
            $table->string('plugin_id', 120)->unique();
            $table->string('name', 191);
            $table->string('version', 120);
            $table->string('core_version_range', 120)->nullable();
            $table->boolean('is_enabled')->default(false)->index();
            $table->char('manifest_hash', 64)->nullable()->index();
            $table->string('manifest_path', 1024)->nullable();
            $table->string('provider_class', 255)->nullable();
            $table->string('frontend_remote_entry', 1024)->nullable();
            $table->string('frontend_remote_scope', 120)->nullable();
            $table->string('frontend_exposed_module', 191)->nullable();
            $table->text('last_error')->nullable();
            $table->text('last_warning')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugins_registry');
    }
};

