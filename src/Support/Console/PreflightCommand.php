<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Прелёт-проверка конфигурации перед тем, как пускать инстанс в работу.
 *
 * Ловит «тихие» конфиг-ошибки, которые иначе всплывают только в проде на редких
 * путях (битый APP_KEY → 419/500 в web-группе; рассинхрон APP_URL и secure-кук →
 * отброшенные куки; недоступная БД/отсутствие ядровых таблиц). Запускается шагом
 * CI перед `up` (fatal) и/или в entrypoint (warn).
 *
 * Exit 0 — всё ок (могут быть предупреждения). Exit 1 — есть блокирующие ошибки.
 */
final class PreflightCommand extends Command
{
    protected $signature = 'polymorph:preflight {--json : Вывести результат как JSON}';

    protected $description = 'Проверить критичную конфигурацию (APP_KEY, cookies, БД) перед запуском.';

    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $warnings = [];

    public function handle(): int
    {
        $this->checkAppKey();
        $this->checkUrlAndCookies();
        $this->checkDatabase();

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(
                ['ok' => $this->errors === [], 'errors' => $this->errors, 'warnings' => $this->warnings],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
            ));

            return $this->errors === [] ? self::SUCCESS : self::FAILURE;
        }

        foreach ($this->warnings as $w) {
            $this->warn('[warn] '.$w);
        }
        foreach ($this->errors as $e) {
            $this->error('[fail] '.$e);
        }

        if ($this->errors !== []) {
            $this->error('Preflight FAILED: '.count($this->errors).' проблем(ы).');

            return self::FAILURE;
        }

        $this->info('Preflight OK'.($this->warnings !== [] ? ' (с предупреждениями)' : '').'.');

        return self::SUCCESS;
    }

    private function checkAppKey(): void
    {
        $key = (string) config('app.key', '');
        $cipher = (string) config('app.cipher', 'AES-256-CBC');
        $need = str_contains($cipher, '256') ? 32 : 16;

        if ($key === '') {
            $this->errors[] = 'APP_KEY пуст. Сгенерируй: php artisan key:generate --show';

            return;
        }

        $raw = str_starts_with($key, 'base64:')
            ? base64_decode(substr($key, 7), true)
            : $key;

        if (! is_string($raw)) {
            $this->errors[] = 'APP_KEY: некорректный base64 после префикса base64:.';

            return;
        }

        if (strlen($raw) !== $need) {
            $this->errors[] = sprintf(
                'APP_KEY: длина ключа %d байт, для %s нужно %d. Вероятно, пропущен префикс base64: или ключ битый.',
                strlen($raw),
                $cipher,
                $need,
            );
        }
    }

    private function checkUrlAndCookies(): void
    {
        $scheme = parse_url((string) config('app.url', ''), PHP_URL_SCHEME);
        $sessionSecure = (bool) config('session.secure');

        if ($scheme === 'http' && $sessionSecure) {
            $this->errors[] = 'APP_URL=http, но SESSION_SECURE_COOKIE=true → браузер отбросит куки (нет сессии, CSRF 419). Используй HTTPS или выключи secure-куки.';
        }

        if ($scheme === 'https' && ! $sessionSecure) {
            $this->warnings[] = 'APP_URL=https, но SESSION_SECURE_COOKIE=false → куки идут без флага Secure. Стоит включить SESSION_SECURE_COOKIE=true.';
        }
    }

    private function checkDatabase(): void
    {
        try {
            $connection = DB::connection();
            $connection->getPdo();
        } catch (\Throwable $exception) {
            $this->errors[] = 'БД недоступна: '.$exception->getMessage();

            return;
        }

        $driver = $connection->getDriverName();
        if ($driver !== 'pgsql') {
            $this->errors[] = 'Polymorph поддерживает только PostgreSQL; настроен драйвер '.$driver.'.';

            return;
        }

        // Таблицы, нужные выбранным драйверам (миграции должны их создать).
        $required = ['dp_records', 'dp_media_edges', 'dp_outbox'];
        if (config('cache.default') === 'database') {
            $required[] = (string) config('cache.stores.database.table', 'cache');
        }
        if (config('session.driver') === 'database') {
            $required[] = (string) config('session.table', 'sessions');
        }
        if (config('queue.default') === 'database') {
            $required[] = 'jobs';
        }

        $tablesAvailable = true;
        foreach (array_unique($required) as $table) {
            try {
                if (! Schema::hasTable($table)) {
                    $tablesAvailable = false;
                    $this->errors[] = "Нет таблицы '{$table}' — прогони миграции (php artisan migrate --force).";
                }
            } catch (\Throwable $exception) {
                $tablesAvailable = false;
                $this->warnings[] = "Не удалось проверить таблицу '{$table}': ".$exception->getMessage();
            }
        }

        if (! $tablesAvailable) {
            return;
        }

        try {
            if (! Schema::hasColumn('dp_outbox', 'dead_lettered_at')) {
                $this->errors[] = "Data Platform baseline is stale: missing column 'dp_outbox.dead_lettered_at'. Back up any required data and rebuild the fresh-only baseline with php artisan migrate:fresh --force.";
            }
        } catch (\Throwable $exception) {
            $this->warnings[] = "Не удалось проверить колонку 'dp_outbox.dead_lettered_at': ".$exception->getMessage();
        }
    }
}
