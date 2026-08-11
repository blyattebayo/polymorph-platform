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
        Schema::create('auth_oauth_clients', function (Blueprint $table): void {
            $table->uuid('client_id')->primary();
            $table->string('name', 200);
            $table->json('redirect_uris');
            $table->timestampTz('created_at', 6);
        });

        Schema::create('auth_oauth_authorization_codes', function (Blueprint $table): void {
            $table->char('credential_hash', 64)->primary();
            $table->uuid('client_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('redirect_uri');
            $table->text('resource');
            $table->string('scope', 100);
            $table->string('code_challenge', 43);
            $table->timestampTz('expires_at', 6)->index();
            $table->timestampTz('created_at', 6);

            $table->foreign('client_id', 'oauth_code_client_fk')
                ->references('client_id')->on('auth_oauth_clients')->cascadeOnDelete();
        });

        Schema::create('auth_oauth_grants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('resource');
            $table->string('scope', 100);
            $table->timestampTz('expires_at', 6)->index();
            $table->timestampTz('created_at', 6);
            $table->timestampTz('updated_at', 6);

            $table->foreign('client_id', 'oauth_grant_client_fk')
                ->references('client_id')->on('auth_oauth_clients')->cascadeOnDelete();
        });

        Schema::create('auth_oauth_refresh_tokens', function (Blueprint $table): void {
            $table->char('credential_hash', 64)->primary();
            $table->uuid('grant_id')->index();
            $table->timestampTz('consumed_at', 6)->nullable();
            $table->timestampTz('created_at', 6);

            $table->foreign('grant_id', 'oauth_refresh_grant_fk')
                ->references('id')->on('auth_oauth_grants')->cascadeOnDelete();
        });

        Schema::create('auth_oauth_access_tokens', function (Blueprint $table): void {
            $table->char('credential_hash', 64)->primary();
            $table->uuid('grant_id')->index();
            $table->timestampTz('expires_at', 6)->index();
            $table->timestampTz('created_at', 6);

            $table->foreign('grant_id', 'oauth_access_grant_fk')
                ->references('id')->on('auth_oauth_grants')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE auth_oauth_clients ADD CONSTRAINT oauth_client_name_not_blank CHECK (btrim(name) <> '')");
        DB::statement("ALTER TABLE auth_oauth_clients ADD CONSTRAINT oauth_client_redirects_array CHECK (jsonb_typeof(redirect_uris::jsonb) = 'array' AND jsonb_array_length(redirect_uris::jsonb) > 0)");
        DB::statement("ALTER TABLE auth_oauth_authorization_codes ADD CONSTRAINT oauth_code_hash_format CHECK (credential_hash ~ '^[0-9a-f]{64}$')");
        DB::statement('ALTER TABLE auth_oauth_authorization_codes ADD CONSTRAINT oauth_code_expiry_after_creation CHECK (expires_at > created_at)');
        DB::statement('ALTER TABLE auth_oauth_grants ADD CONSTRAINT oauth_grant_expiry_after_creation CHECK (expires_at > created_at)');
        DB::statement("ALTER TABLE auth_oauth_refresh_tokens ADD CONSTRAINT oauth_refresh_hash_format CHECK (credential_hash ~ '^[0-9a-f]{64}$')");
        DB::statement('ALTER TABLE auth_oauth_refresh_tokens ADD CONSTRAINT oauth_refresh_consumed_after_creation CHECK (consumed_at IS NULL OR consumed_at >= created_at)');
        DB::statement("ALTER TABLE auth_oauth_access_tokens ADD CONSTRAINT oauth_access_hash_format CHECK (credential_hash ~ '^[0-9a-f]{64}$')");
        DB::statement('ALTER TABLE auth_oauth_access_tokens ADD CONSTRAINT oauth_access_expiry_after_creation CHECK (expires_at > created_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_oauth_access_tokens');
        Schema::dropIfExists('auth_oauth_refresh_tokens');
        Schema::dropIfExists('auth_oauth_grants');
        Schema::dropIfExists('auth_oauth_authorization_codes');
        Schema::dropIfExists('auth_oauth_clients');
    }
};
