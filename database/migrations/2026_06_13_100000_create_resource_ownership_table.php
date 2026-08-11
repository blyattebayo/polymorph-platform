<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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

    }

    public function down(): void
    {
        Schema::dropIfExists('resource_ownership');
    }
};
