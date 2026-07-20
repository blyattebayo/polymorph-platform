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
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->uuid('family_id')->index();
            $table->foreignId('parent_id')->nullable()->constrained('auth_sessions')->nullOnDelete();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('rotated_at')->nullable();
            $table->timestamp('expires_at')->index();
            // Жёсткий потолок жизни семьи refresh-сессий: ротация не продлевает его.
            $table->timestamp('absolute_expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->string('ip', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at', 'expires_at'], 'auth_sessions_user_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_sessions');
    }
};
