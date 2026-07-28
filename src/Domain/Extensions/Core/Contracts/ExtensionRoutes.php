<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Core\Contracts;

use Polymorph\Platform\Domain\Extensions\Core\ValueObjects\DiscoveredExtension;

/**
 * Порт: что жизненный цикл расширения делает с его маршрутами.
 *
 * Реализация своя у каждого движка маршрутизации и биндится его провайдером —
 * поэтому ExtensionManager не знает, какой движок работает, а «включилось,
 * но маршрутов нет» не может возникнуть из-за расхождения двух реализаций.
 */
interface ExtensionRoutes
{
    /**
     * Проверить файл маршрутов до того, как расширение будет включено.
     *
     * @throws \Throwable если файл нельзя смонтировать
     */
    public function validate(DiscoveredExtension $plugin): void;

    /**
     * Вживить маршруты в РАБОТАЮЩИЙ роутер: bootstrap этого процесса прошёл
     * без только что включённого расширения, и без этого шага его пути
     * отвечали бы 404 до следующего запроса.
     *
     * Идемпотентно: повторный вызов для уже смонтированного расширения
     * не дублирует маршруты.
     */
    public function mountInCurrentRouter(DiscoveredExtension $plugin): void;
}
