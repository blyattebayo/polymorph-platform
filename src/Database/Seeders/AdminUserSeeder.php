<?php

declare(strict_types=1);

namespace Polymorph\Platform\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\Roles\Services\AccessProvisioner;
use Polymorph\Platform\Domain\Users\Core\Models\User;

final class AdminUserSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'admin123';

    public function run(AccessProvisioner $provisioner): void
    {
        $email = (string) config('admin.seed.email', 'admin@example.com');
        $password = (string) config('admin.seed.password', self::DEFAULT_PASSWORD);
        $name = (string) config('admin.seed.name', 'Administrator');

        DB::transaction(function () use ($email, $name, $password, $provisioner): void {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make($password),
                ],
            );

            $adminRoleId = (int) (DB::table('roles')
                ->where('code', BuiltInRoleCatalog::ROLE_SYSTEM_ADMIN)
                ->value('id') ?? 0);
            if ($adminRoleId <= 0) {
                throw new \RuntimeException('The system.admin role must exist before the admin user is seeded.');
            }

            DB::table('user_role_assignments')->updateOrInsert(
                ['user_id' => (int) $user->id, 'role_id' => $adminRoleId],
                ['updated_at' => now(), 'created_at' => now()],
            );
            $provisioner->syncForUser($user);
        });

        $this->command?->info("Admin user: {$email}");

        if ($password === self::DEFAULT_PASSWORD) {
            $this->command?->warn('Admin password is the DEFAULT (admin123) — set ADMIN_PASSWORD for anything beyond local demo.');
        }
    }
}
