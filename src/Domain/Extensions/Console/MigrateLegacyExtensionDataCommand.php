<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Console;

use Polymorph\Platform\Domain\DataPlatform\StorageKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cutover-миграция данных расширения с V1-неймспейса на V2 (Stage 4).
 *
 *  - schemas.code:            plugin__{id}__{entity}  →  ext__{id}__{entity}
 *  - policies.resource_pattern: plugin.{id}.{...}     →  ext.{id}.{...}
 *
 * Записи (records) переезжают автоматически — они привязаны к schema_id, не к коду.
 * Идемпотентно: повторный прогон ничего не делает. По умолчанию в транзакции;
 * --dry-run только показывает, что будет переименовано.
 */
final class MigrateLegacyExtensionDataCommand extends Command
{
    protected $signature = 'extensions:migrate-legacy {id : ID расширения (например purr_quest)} {--dry-run : Показать без изменений}';

    protected $description = 'Перенести данные/ACL расширения с V1-неймспейса (plugin__/plugin.) на V2 (ext__/ext.)';

    public function handle(): int
    {
        $id = (string) $this->argument('id');
        if (trim($id) === '') {
            $this->error('Extension id is required.');

            return self::FAILURE;
        }

        $schemaFromPrefix = 'plugin__' . $id . '__';
        $schemaToPrefix = StorageKey::schemaPrefix($id); // ext__{id}__
        $aclFromPrefix = 'plugin.' . $id . '.';
        $aclToPrefix = 'ext.' . $id . '.';

        $schemas = $this->prefixedRows('schemas', 'code', $schemaFromPrefix);
        $policies = $this->prefixedRows('ac_policies', 'resource_pattern', $aclFromPrefix);

        $this->info("Schemas to rename: {$schemas->count()} ({$schemaFromPrefix}* → {$schemaToPrefix}*)");
        $this->info("ACL policies to rename: {$policies->count()} ({$aclFromPrefix}* → {$aclToPrefix}*)");

        if ($this->option('dry-run')) {
            foreach ($schemas as $row) {
                $this->line("  schema #{$row->id}: {$row->code} → " . $this->reprefix((string) $row->code, $schemaFromPrefix, $schemaToPrefix));
            }
            foreach ($policies as $row) {
                $this->line("  policy #{$row->id}: {$row->resource_pattern} → " . $this->reprefix((string) $row->resource_pattern, $aclFromPrefix, $aclToPrefix));
            }
            $this->comment('Dry-run: ничего не изменено.');

            return self::SUCCESS;
        }

        if ($schemas->isEmpty() && $policies->isEmpty()) {
            $this->comment('Нечего мигрировать (уже на V2 или расширение не сеялось).');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($schemas, $policies, $schemaFromPrefix, $schemaToPrefix, $aclFromPrefix, $aclToPrefix): void {
            foreach ($schemas as $row) {
                DB::table('schemas')->where('id', $row->id)->update([
                    'code' => $this->reprefix((string) $row->code, $schemaFromPrefix, $schemaToPrefix),
                ]);
            }
            foreach ($policies as $row) {
                DB::table('ac_policies')->where('id', $row->id)->update([
                    'resource_pattern' => $this->reprefix((string) $row->resource_pattern, $aclFromPrefix, $aclToPrefix),
                ]);
            }
        });

        $this->info("Готово: схем {$schemas->count()}, политик {$policies->count()} перенесено на V2.");

        return self::SUCCESS;
    }

    /**
     * Строки таблицы, у которых $column начинается с $prefix (LIKE с экранированием
     * '_'/'%', т.к. в префиксах есть '_' — иначе over-match по jsonb-grammar).
     *
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    private function prefixedRows(string $table, string $column, string $prefix): \Illuminate\Support\Collection
    {
        $escaped = addcslashes($prefix, '%_\\');

        return DB::table($table)
            ->where($column, 'like', $escaped . '%')
            ->get(['id', $column]);
    }

    private function reprefix(string $value, string $from, string $to): string
    {
        return str_starts_with($value, $from) ? $to . substr($value, strlen($from)) : $value;
    }
}
