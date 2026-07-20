<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plugins_registry', function (Blueprint $table): void {
            if (! Schema::hasColumn('plugins_registry', 'frontend_bundle')) {
                $table->string('frontend_bundle', 1024)->nullable()->after('provider_class');
            }

            if (Schema::hasColumn('plugins_registry', 'frontend_remote_entry')) {
                $table->dropColumn('frontend_remote_entry');
            }

            if (Schema::hasColumn('plugins_registry', 'frontend_remote_scope')) {
                $table->dropColumn('frontend_remote_scope');
            }

            if (Schema::hasColumn('plugins_registry', 'frontend_exposed_module')) {
                $table->dropColumn('frontend_exposed_module');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plugins_registry', function (Blueprint $table): void {
            if (! Schema::hasColumn('plugins_registry', 'frontend_remote_entry')) {
                $table->string('frontend_remote_entry', 1024)->nullable()->after('provider_class');
            }

            if (! Schema::hasColumn('plugins_registry', 'frontend_remote_scope')) {
                $table->string('frontend_remote_scope', 120)->nullable()->after('frontend_remote_entry');
            }

            if (! Schema::hasColumn('plugins_registry', 'frontend_exposed_module')) {
                $table->string('frontend_exposed_module', 191)->nullable()->after('frontend_remote_scope');
            }

            if (Schema::hasColumn('plugins_registry', 'frontend_bundle')) {
                $table->dropColumn('frontend_bundle');
            }
        });
    }
};
