<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_index_reconciliation_requests', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->unsignedBigInteger('generation')->default(1);
            $table->timestampsTz();

            $table->unique(['target_type', 'target_id'], 'record_index_reconcile_target_unique');
            $table->index('updated_at', 'record_index_reconcile_updated_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_index_reconciliation_requests');
    }
};
