<?php

declare(strict_types=1);

namespace Polymorph\Platform\Database\Seeders;

use Illuminate\Database\Seeder;
use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\Roles\Core\Models\Role;

/**
 * Идемпотентно создаёт built-in роли платформы из BuiltInRoleCatalog.
 * Живёт в пакете: тонкий хост получает базовую авторизацию без копирования сидеров.
 */
class BuiltInRolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (BuiltInRoleCatalog::definitions() as $role) {
            Role::query()->updateOrCreate(
                ['code' => $role['code']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                ],
            );
        }
    }
}
