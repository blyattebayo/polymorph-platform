<?php

declare(strict_types=1);

return [
    // Runtime scans only installed extension artifacts in this directory.
    'root_path' => (static function (): string {
        $configured = env('PLUGINS_ROOT_PATH');
        if (is_string($configured) && $configured !== '') {
            $isAbsolute = str_starts_with($configured, '/')
                || (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $configured);

            return $isAbsolute ? $configured : base_path($configured);
        }

        return env('APP_ENV') === 'testing'
            ? storage_path('app/plugins')
            : base_path('../plugins');
    })(),

    // Build reads editable extension sources from here; runtime never scans it.
    'src_root' => env('PLUGINS_SRC_PATH', base_path('plugins-src')),
    'build_output' => env('PLUGINS_BUILD_OUTPUT', storage_path('app/plugin-builds')),

    'php_scoper_path' => env('PLUGINS_PHP_SCOPER_PATH', dirname(__DIR__).'/tools/php-scoper.phar'),
    'php_scoper_config' => env('PLUGINS_PHP_SCOPER_CONFIG', dirname(__DIR__).'/tools/scoper/plugin-scoper.inc.php'),
    'php_scoper_prefix_base' => env('PLUGINS_PHP_SCOPER_PREFIX_BASE', 'PolymorphScoped'),

    // Artifact extraction is staged outside runtime_root before atomic replacement.
    'staging_path' => env('PLUGINS_STAGING_PATH', storage_path('app/plugin-staging')),
    'scaffold_template_path' => env('PLUGINS_SCAFFOLD_TEMPLATE_PATH', dirname(__DIR__).'/resources/plugin-template'),

    'allowed_admin_prefix_template' => 'api/v1/admin/plugins/%s',
    'allowed_api_prefix_template' => 'api/v1/plugins/%s',
    'allowed_web_prefix_template' => 'plugins/%s',

    'default_frontend_section' => 'content',
    'default_frontend_ui_mode' => 'overlay',
    'core_version' => env('APP_VERSION', '0.0.0'),
];
