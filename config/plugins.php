<?php

declare(strict_types=1);

return [
    // Каталог установленных плагинов. В dev по умолчанию — репозиторная папка
    // ../plugins (где сейчас живут исходники); под тестами — изолированный
    // storage/app/plugins (пустой), чтобы core-suite не подхватывал in-repo
    // плагины (их providers/миграции). Переопределяется PLUGINS_ROOT_PATH —
    // именно туда будут распаковываться скачанные плагины.
    'root_path' => (static function (): string {
        $configured = env('PLUGINS_ROOT_PATH');
        if (is_string($configured) && $configured !== '') {
            // Абсолютные пути (POSIX или Windows) пропускаем как есть; относительные
            // резолвим от base_path, чтобы CWD php-fpm/воркера не искажал discovery
            // (иначе is_dir('storage/app/plugins') зависит от рабочего каталога процесса).
            $isAbsolute = str_starts_with($configured, '/')
                || (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $configured);

            return $isAbsolute ? $configured : base_path($configured);
        }

        return env('APP_ENV') === 'testing'
            ? storage_path('app/plugins')
            : base_path('../plugins');
    })(),

    // Каталог ИСХОДНИКОВ плагинов, которые разработчик редактирует и собирает
    // (plugins:build читает отсюда). Рантайм его НЕ сканирует — он видит только
    // распакованные drop-in артефакты в root_path. Версионируется в git.
    'src_root' => env('PLUGINS_SRC_PATH', base_path('plugins-src')),

    // Куда plugins:build кладёт собранные .zip-артефакты (+ .sha256 рядом).
    // Отсюда их подхватывает plugins:install (LocalZipSource) или будущий маркетплейс.
    'build_output' => env('PLUGINS_BUILD_OUTPUT', storage_path('app/plugin-builds')),

    // Изоляция vendor плагина (php-scoper): путь к phar и статичному конфигу.
    // plugins:build префиксует namespace сторонних зависимостей плагина, чтобы
    // их версии не конфликтовали с ядром/другими плагинами (общий автолоадер).
    'php_scoper_path' => env('PLUGINS_PHP_SCOPER_PATH', dirname(__DIR__).'/tools/php-scoper.phar'),
    'php_scoper_config' => env('PLUGINS_PHP_SCOPER_CONFIG', dirname(__DIR__).'/tools/scoper/plugin-scoper.inc.php'),
    'php_scoper_prefix_base' => env('PLUGINS_PHP_SCOPER_PREFIX_BASE', 'PolymorphScoped'),

    // Каталог распаковки артефакта ПЕРЕД атомарной заменой. Держим его ВНЕ
    // runtime_root, чтобы рекурсивный file-watcher (IDE/dev-сервер), следящий за
    // plugins/, не лочил свежераспакованные файлы и не ронял swap. Должен быть на
    // том же томе, что и runtime_root (иначе install сделает copy-fallback).
    'staging_path' => env('PLUGINS_STAGING_PATH', storage_path('app/plugin-staging')),

    'scaffold_template_path' => env('PLUGINS_SCAFFOLD_TEMPLATE_PATH', dirname(__DIR__).'/resources/plugin-template'),

    'manifest_file' => 'plugin.json',

    'allowed_admin_prefix_template' => 'api/v1/admin/plugins/%s',
    'allowed_api_prefix_template' => 'api/v1/plugins/%s',
    'allowed_web_prefix_template' => 'plugins/%s',

    'default_frontend_section' => 'content',

    // Режим отображения плагина в админ-SPA по умолчанию, если в манифесте не
    // объявлено contributes.frontend.ui.mode (V2) / frontend.ui.mode (V1).
    // 'overlay' — полноэкранный модальный оверлей поверх ядра; 'embedded' — inline.
    'default_frontend_ui_mode' => 'overlay',

    'core_version' => env('APP_VERSION', '0.0.0'),

    'compatibility' => [
        // Строгий режим: enable блокируется, если coreVersionRange плагина
        // не покрывает текущую версию ядра. false — только предупреждение
        // (для локальной разработки).
        'strict' => env('PLUGINS_STRICT_COMPATIBILITY', true),
    ],
];
