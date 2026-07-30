<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Явный маркер встроенной учётки платформенного администратора.
 *
 * До него «неприкасаемость» выводилась из членства в роли system.admin, и любой
 * пользователь, получивший роль, становился нередактируемым навсегда
 * (односторонняя дверь). Флаг разводит два понятия: platform admin — встроенная
 * учётка (создаёт AdminUserSeeder), которую нельзя трогать через админ-API;
 * system.admin — обычная снимаемая роль супер-доступа.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_platform_admin');
        });
    }
};
