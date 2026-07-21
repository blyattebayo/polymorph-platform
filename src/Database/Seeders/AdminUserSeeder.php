<?php

declare(strict_types=1);

namespace Polymorph\Platform\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\Users\Core\Models\User;

/**
 * Идемпотентно создаёт администратора платформы и назначает ему built-in роль
 * system-admin. Учётка берётся из config('admin.seed') (env ADMIN_EMAIL /
 * ADMIN_PASSWORD / ADMIN_NAME) — читается через config, а не env(), чтобы
 * работать и под `config:cache`. Требует предварительно засеянных ролей
 * (BuiltInRolesSeeder) — PlatformSeeder гарантирует порядок.
 */
class AdminUserSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'admin123';

    public function run(): void
    {
        $email = (string) config('admin.seed.email', 'admin@example.com');
        $password = (string) config('admin.seed.password', self::DEFAULT_PASSWORD);
        $name = (string) config('admin.seed.name', 'Administrator');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ],
        );

        $userId = (int) (User::query()->where('email', $email)->value('id') ?? 0);
        $adminRoleId = (int) (DB::table('roles')->where('code', BuiltInRoleCatalog::ROLE_SYSTEM_ADMIN)->value('id') ?? 0);

        if ($userId > 0 && $adminRoleId > 0) {
            DB::table('user_role_assignments')->updateOrInsert(
                ['user_id' => $userId, 'role_id' => $adminRoleId],
                ['updated_at' => now(), 'created_at' => now()],
            );
        }

        $this->command?->info("Admin user: {$email}");

        if ($password === self::DEFAULT_PASSWORD) {
            $this->command?->warn('Admin password is the DEFAULT (admin123) — set ADMIN_PASSWORD for anything beyond local demo.');
        }
    }
}
