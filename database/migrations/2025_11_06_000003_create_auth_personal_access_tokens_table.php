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
        Schema::create('auth_personal_access_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->string('secret_digest', 64);
            $table->string('display_hint', 64);
            $table->json('scopes');
            $table->timestampTz('issued_at', 6);
            $table->timestampTz('expires_at', 6);
            $table->timestampTz('revoked_at', 6)->nullable();
            $table->unsignedBigInteger('revoked_by_user_id')->nullable();
            $table->string('revocation_reason', 64)->nullable();
            $table->timestampTz('last_used_at', 6)->nullable();

            $table->foreign('user_id', 'pat_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
            $table->unique('secret_digest', 'pat_secret_digest_uq');
            $table->index(['user_id', 'revoked_at', 'expires_at'], 'pat_owner_status_idx');
            $table->index(['revoked_at', 'expires_at'], 'pat_status_idx');
            $table->index('issued_at', 'pat_issued_at_idx');
        });

        DB::statement("ALTER TABLE auth_personal_access_tokens ADD CONSTRAINT pat_name_not_blank CHECK (btrim(name) <> '')");
        DB::statement("ALTER TABLE auth_personal_access_tokens ADD CONSTRAINT pat_digest_format CHECK (secret_digest ~ '^[0-9a-f]{64}$')");
        DB::statement("ALTER TABLE auth_personal_access_tokens ADD CONSTRAINT pat_hint_not_blank CHECK (btrim(display_hint) <> '')");
        DB::statement("ALTER TABLE auth_personal_access_tokens ADD CONSTRAINT pat_scopes_non_empty_array CHECK (jsonb_typeof(scopes::jsonb) = 'array' AND jsonb_array_length(scopes::jsonb) > 0)");
        DB::statement('ALTER TABLE auth_personal_access_tokens ADD CONSTRAINT pat_expiry_after_issue CHECK (expires_at > issued_at)');
        DB::statement(<<<'SQL'
            ALTER TABLE auth_personal_access_tokens
            ADD CONSTRAINT pat_revocation_complete
            CHECK (
                (revoked_at IS NULL AND revoked_by_user_id IS NULL AND revocation_reason IS NULL)
                OR
                (revoked_at IS NOT NULL AND revoked_by_user_id IS NOT NULL AND revocation_reason IS NOT NULL)
            )
        SQL);
        DB::statement('ALTER TABLE auth_personal_access_tokens ADD CONSTRAINT pat_revocation_after_issue CHECK (revoked_at IS NULL OR revoked_at >= issued_at)');
        DB::statement('ALTER TABLE auth_personal_access_tokens ADD CONSTRAINT pat_usage_after_issue CHECK (last_used_at IS NULL OR last_used_at >= issued_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_personal_access_tokens');
    }
};
