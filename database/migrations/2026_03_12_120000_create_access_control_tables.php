<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ac_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('resource_pattern', 500)->index();
            $table->string('action', 50)->index();
            $table->string('effect', 16)->index();
            $table->unsignedInteger('priority')->default(100)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->char('matcher_hash', 64)->unique();
            $table->timestamps();

            $table->index(['resource_pattern', 'action', 'effect', 'is_active', 'priority'], 'ac_policies_hot_idx');
        });

        Schema::create('ac_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('policy_id')->constrained('ac_policies')->cascadeOnDelete();
            $table->string('subject', 255)->index();
            $table->timestamps();

            $table->unique(['policy_id', 'subject']);
        });

        Schema::create('ac_compiled', function (Blueprint $table): void {
            $table->id();
            $table->string('subject', 255)->index();
            $table->string('resource_pattern', 500)->index();
            $table->string('action', 50)->index();
            $table->string('effect', 16)->index();
            $table->unsignedInteger('priority')->index();
            $table->foreignId('policy_id')->nullable()->constrained('ac_policies')->nullOnDelete();
            $table->timestamp('compiled_at')->useCurrent()->index();

            $table->index(['subject', 'action'], 'ac_compiled_subject_action_idx');
            $table->index(['subject', 'resource_pattern', 'action', 'priority'], 'ac_compiled_lookup_idx');
        });

        Schema::create('ac_audit', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 100)->index();
            $table->string('actor', 255)->index();
            $table->string('target_subject', 255)->nullable()->index();
            $table->foreignId('policy_id')->nullable()->constrained('ac_policies')->nullOnDelete();
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ac_audit');
        Schema::dropIfExists('ac_compiled');
        Schema::dropIfExists('ac_assignments');
        Schema::dropIfExists('ac_policies');
    }
};