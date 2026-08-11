<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * Админское API ядра.
 *
 * Общий префикс пути, префикс имени и аутентификация заданы здесь ОДИН раз —
 * файлы ниже описывают только свои маршруты и свои капабилити. Список явный,
 * а не glob: порядок регистрации виден, и добавление файла — осознанный шаг.
 *
 * Коллизии method+uri и дубли имён ловит `php artisan routing:lint`.
 */
Route::middleware(['api', 'auth:session'])
    ->prefix('api/v1/admin')
    ->name('admin.v1.')
    ->group(function (): void {
        foreach ([
            'acl',
            'media',
            'menu',
            'plugins',
            'record_definitions',
            'records',
            'schema_models',
            'table_configs',
            'users_roles',
        ] as $file) {
            require __DIR__."/admin/{$file}.php";
        }
    });
