<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Настраиваемые меню (домен Polymorph\Platform\Domain\Menu).
 *
 * Каждое меню — это дерево пунктов, хранимое одной строкой по строковому
 * ключу (например 'primary', 'system'). Порядок и вложенность — структура
 * самого JSON; новые меню добавляются просто новым ключом. Бэкенд не
 * интерпретирует содержимое узлов — дефолты и фильтрация по правам на фронте.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('tree');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
