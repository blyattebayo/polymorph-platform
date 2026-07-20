<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * ADR 0006 — Stage 1 / Increment A: rename the persisted controller-namespace prefix
 * `App\` -> `Polymorph\Platform\` in route_nodes, mirroring the source-code namespace rename.
 *
 * Two columns hold FQCN prefixes written by clients via the dynamic-route UI:
 *   - action_meta->>'action' (json)  — CONTROLLER action string, e.g. 'App\Domain\...\FooController@show'
 *   - namespace              (varchar) — optional route-group namespace prefix
 *
 * Postgres notes: E'...' literals interpret \\ as a single backslash regardless of
 * standard_conforming_strings; left()= guards are used instead of LIKE (backslash is LIKE's
 * escape char); action_meta is json, so cast ::jsonb for jsonb_set then back to ::json.
 * On the test DB (RefreshDatabase) route_nodes is empty here, so this is a safe no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE route_nodes
            SET action_meta = jsonb_set(
                    action_meta::jsonb,
                    '{action}',
                    to_jsonb(E'Polymorph\\Platform\\' || substr(action_meta->>'action', 5))
                )::json
            WHERE action_type = 'controller'
              AND left(action_meta->>'action', 4) = E'App\\'
        SQL);

        DB::statement(<<<'SQL'
            UPDATE route_nodes
            SET namespace = E'Polymorph\\Platform\\' || substr(namespace, 5)
            WHERE namespace IS NOT NULL
              AND left(namespace, 4) = E'App\\'
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            UPDATE route_nodes
            SET action_meta = jsonb_set(
                    action_meta::jsonb,
                    '{action}',
                    to_jsonb(E'App\\' || substr(action_meta->>'action', length(E'Polymorph\\Platform\\') + 1))
                )::json
            WHERE action_type = 'controller'
              AND left(action_meta->>'action', length(E'Polymorph\\Platform\\')) = E'Polymorph\\Platform\\'
        SQL);

        DB::statement(<<<'SQL'
            UPDATE route_nodes
            SET namespace = E'App\\' || substr(namespace, length(E'Polymorph\\Platform\\') + 1)
            WHERE namespace IS NOT NULL
              AND left(namespace, length(E'Polymorph\\Platform\\')) = E'Polymorph\\Platform\\'
        SQL);
    }
};
