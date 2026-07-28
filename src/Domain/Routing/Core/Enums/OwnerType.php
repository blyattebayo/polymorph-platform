<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Core\Enums;

/**
 * Тип владельца маршрута.
 *
 * Определяет источник создания маршрута для приоритизации загрузки
 * и контроля доступа к операциям.
 */
enum OwnerType: string
{
    /**
     * Системные маршруты (декларативные из файлов).
     *
     * - Загружаются первыми (sort_order: -10000 до -1)
     * - Всегда readonly
     * - Не могут быть изменены или переупорядочены
     * - Формат: 'system'
     */
    case SYSTEM = 'system';

    /**
     * Маршруты плагинов.
     *
     * - Загружаются вторыми (sort_order: 0 до 9999)
     * - Обычно readonly
     * - Привязаны к конкретному плагину
     * - Формат: 'plugin:plugin-name'
     */
    case PLUGIN = 'plugin';

    /**
     * Пользовательские маршруты (созданные через API).
     *
     * - Загружаются последними (sort_order: 10000+)
     * - Могут быть изменены и переупорядочены
     * - Управляются через админ-панель
     * - Формат: 'client'
     */
    case CLIENT = 'client';

    /**
     * Создать композитное значение owner.
     *
     * @param  self  $type  Тип владельца
     * @param  string|null  $id  ID владельца (для PLUGIN - обязателен)
     * @return string Композитное значение для поля owner
     *
     * @example OwnerType::make(OwnerType::SYSTEM) в†’ 'system'
     * @example OwnerType::make(OwnerType::PLUGIN, 'blog') в†’ 'plugin:blog'
     * @example OwnerType::make(OwnerType::CLIENT) в†’ 'client'
     */
    public static function make(self $type, ?string $id = null): string
    {
        if ($type === self::PLUGIN && $id === null) {
            throw new \InvalidArgumentException('Plugin owner type requires an ID');
        }

        return $id ? "{$type->value}:{$id}" : $type->value;
    }

    /**
     * Определить тип владельца по значению поля owner.
     *
     * Возвращает null для нераспознанного значения вместо \ValueError: owner
     * читается на пути регистрации маршрутов в boot(), и одна мусорная строка
     * в route_nodes (сидер, ручной SQL, миграция) не должна ронять приложение.
     * Вызывающий сам решает, что делать с неизвестным владельцем.
     *
     * @param  string|null  $owner  Значение поля owner ('system', 'plugin:blog', 'client')
     *
     * @example OwnerType::typeFrom('plugin:blog') → PLUGIN
     * @example OwnerType::typeFrom('bogus') → null
     */
    public static function typeFrom(?string $owner): ?self
    {
        if ($owner === null) {
            return null;
        }

        return self::tryFrom(explode(':', $owner, 2)[0]);
    }
}
