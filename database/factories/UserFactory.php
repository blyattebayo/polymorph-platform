<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\Users\Core\Models\User;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<User>
     */
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
        ];
    }

    /**
     * Indicate that the user has the system administrator role.
     */
    public function systemAdmin(): static
    {
        return $this->afterCreating(function (User $user): void {
            $adminRoleId = (int) (DB::table('roles')->where('code', BuiltInRoleCatalog::ROLE_SYSTEM_ADMIN)->value('id') ?? 0);
            if ($adminRoleId <= 0) {
                return;
            }

            DB::table('user_role_assignments')->updateOrInsert(
                ['user_id' => (int) $user->id, 'role_id' => $adminRoleId],
                ['updated_at' => now(), 'created_at' => now()],
            );
        });
    }
}
