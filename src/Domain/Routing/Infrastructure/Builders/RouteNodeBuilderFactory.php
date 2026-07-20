<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Builders;

use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Фабрика для создания билдеров узлов маршрутов.
 *
 * Выбирает правильный билдер на основе типа узла (kind).
 * Поддерживает регистрацию билдеров для разных типов узлов.
 */
class RouteNodeBuilderFactory
{
    /**
     * Зарегистрированные билдеры.
     *
     * Массив, где ключ - значение RouteNodeKind (строка), значение - экземпляр билдера.
     *
     * @var array<string, RouteNodeBuilderInterface>
     */
    private array $builders = [];

    public function __construct(
        private readonly AppLogger $logger,
    ) {}

    /**
     * Создать фабрику с предустановленными билдерами.
     *
     * Регистрирует билдеры по умолчанию:
     * - RouteNodeKind::GROUP в†’ RouteGroupNodeBuilder
     * - RouteNodeKind::ROUTE в†’ RouteRouteNodeBuilder
     */
    public static function createDefault(): self
    {
        $factory = new self(app(AppLogger::class));
        $factory->register(RouteNodeKind::GROUP, new RouteGroupNodeBuilder);
        $factory->register(RouteNodeKind::ROUTE, new RouteRouteNodeBuilder);

        return $factory;
    }

    /**
     * Зарегистрировать билдер для типа узла.
     *
     * @param  \Polymorph\Platform\Domain\Routing\Enums\RouteNodeKind  $kind  Тип узла
     * @param  \Polymorph\Platform\Domain\Routing\Builders\RouteNodeBuilderInterface  $builder  Билдер
     */
    public function register(RouteNodeKind $kind, RouteNodeBuilderInterface $builder): void
    {
        $this->builders[$kind->value] = $builder;
    }

    /**
     * Создать билдер для указанного типа узла.
     *
     * Возвращает зарегистрированный билдер для указанного kind.
     * Если билдер не найден, логирует предупреждение и возвращает null.
     *
     * @param  \Polymorph\Platform\Domain\Routing\Enums\RouteNodeKind  $kind  Тип узла
     * @return \Polymorph\Platform\Domain\Routing\Builders\RouteNodeBuilderInterface|null Билдер или null, если не найден
     */
    public function create(RouteNodeKind $kind): ?RouteNodeBuilderInterface
    {
        if (! isset($this->builders[$kind->value])) {
            $this->logger->warning('routing.builder_missing', [
                'kind' => $kind->value,
                'available_kinds' => array_keys($this->builders),
            ]);

            return null;
        }

        return $this->builders[$kind->value];
    }
}
