<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Единая конвенция capability (аудит, этап 1): действие никогда не кодируется
 * в имени ресурса. Переименовывает сохранённые политики и компилированный кэш;
 * matcher_hash пересчитывается той же формулой, что в PolicyData::fromInput.
 *
 * Обратной миграции нет намеренно: down() восстановил бы старые имена только
 * для перечисленных пар и молча потерял бы политики, созданные уже в новой
 * конвенции.
 */
return new class extends Migration
{
    /** @var array<string, array{string, string}> старый resource (action=access) => [новый resource, новый action] */
    private const MAP = [
        'user.read' => ['user', 'read'],
        'user.lifecycle' => ['user', 'manage'],
        'role.read' => ['role', 'read'],
        'role.lifecycle' => ['role', 'manage'],
        'policy.manage' => ['acl.policy', 'manage'],
        'policy.assign' => ['acl.assignment', 'manage'],
        'menu.manage' => ['menu', 'manage'],
        'table_config.manage' => ['table_config', 'manage'],
        'schema.manage' => ['schema', 'manage'],
        'plugin.manage' => ['plugins', 'manage'],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (self::MAP as $oldResource => [$newResource, $newAction]) {
                $rows = DB::table('ac_policies')
                    ->where('resource_pattern', $oldResource)
                    ->where('action', 'access')
                    ->get();

                foreach ($rows as $row) {
                    $hash = hash('sha256', json_encode([
                        'resource_pattern' => $newResource,
                        'action' => $newAction,
                        'effect' => (string) $row->effect,
                        'priority' => (int) $row->priority,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

                    $duplicateId = DB::table('ac_policies')
                        ->where('matcher_hash', $hash)
                        ->where('id', '!=', $row->id)
                        ->value('id');

                    if ($duplicateId !== null) {
                        // Целевая политика уже существует: переносим назначения
                        // на неё и удаляем старую, иначе упрёмся в unique-хеш.
                        $existingSubjects = DB::table('ac_assignments')
                            ->where('policy_id', $duplicateId)
                            ->pluck('subject')
                            ->all();

                        DB::table('ac_assignments')
                            ->where('policy_id', $row->id)
                            ->whereIn('subject', $existingSubjects)
                            ->delete();

                        DB::table('ac_assignments')
                            ->where('policy_id', $row->id)
                            ->update(['policy_id' => $duplicateId]);

                        DB::table('ac_policies')->where('id', $row->id)->delete();

                        continue;
                    }

                    DB::table('ac_policies')->where('id', $row->id)->update([
                        'resource_pattern' => $newResource,
                        'action' => $newAction,
                        'matcher_hash' => $hash,
                    ]);
                }

                DB::table('ac_compiled')
                    ->where('resource_pattern', $oldResource)
                    ->where('action', 'access')
                    ->update([
                        'resource_pattern' => $newResource,
                        'action' => $newAction,
                    ]);
            }
        });
    }
};
