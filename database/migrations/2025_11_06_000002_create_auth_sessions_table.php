<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('credential_hash', 64)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('created_at', 6);
            $table->timestampTz('expires_at', 6)->index();
            $table->string('ip', 64)->nullable();
            $table->text('user_agent')->nullable();

            $table->index(['user_id', 'expires_at'], 'auth_sessions_user_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_sessions');
    }
};
