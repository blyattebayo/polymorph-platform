<?php

declare(strict_types=1);

namespace Polymorph\Platform\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Единая точка сидинга платформы для тонких хостов: built-in роли и политики
 * плюс администратор. Хостовой DatabaseSeeder вызывает только этот класс —
 * `composer require` + `migrate --seed` дают готовую к логину систему.
 */
class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Политики сами вызывают BuiltInRolesSeeder первым.
            BuiltInPoliciesSeeder::class,
            // Админ назначается на роль system-admin — роли уже существуют.
            AdminUserSeeder::class,
        ]);
    }
}
