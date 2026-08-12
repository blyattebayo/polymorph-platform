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
        Schema::create('ac_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('resource_pattern', 500)->index();
            $table->string('action', 50)->index();
            $table->string('effect', 16)->index();
            $table->timestamps();

            $table->unique(['resource_pattern', 'action', 'effect'], 'ac_policies_rule_unique');
        });

        Schema::create('ac_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('policy_id')->constrained('ac_policies')->cascadeOnDelete();
            $table->string('subject', 255)->index();
            $table->timestamps();

            $table->unique(['policy_id', 'subject']);
        });

        $subjectPattern = '^(user:[1-9][0-9]*|role:[a-z][a-z0-9_.-]*)$';
        DB::statement("ALTER TABLE ac_assignments ADD CONSTRAINT ac_assignments_subject_check CHECK (subject ~ '{$subjectPattern}')");
    }

    public function down(): void
    {
        Schema::dropIfExists('ac_assignments');
        Schema::dropIfExists('ac_policies');
    }
};
