<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Registrars;

use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Фабрика для создания регистраторов узлов маршрутов.
 *
 * Выбирает правильный регистратор на основе типа узла (kind).
 * Поддерживает регистрацию регистраторов для разных типов узлов.
 */
class RouteNodeRegistrarFactory
{
    /**
     * Зарегистрированные регистраторы.
     *
     * Массив, где ключ - значение RouteNodeKind (строка), значение - экземпляр регистратора.
     *
     * @var array<string, RouteNodeRegistrarInterface>
     */
    private array $registrars = [];

    public function __construct(
        private readonly AppLogger $logger,
    ) {}

    /**
     * Зарегистрировать регистратор для типа узла.
     *
     * @param  \Polymorph\Platform\Domain\Routing\Enums\RouteNodeKind  $kind  Тип узла
     * @param  \Polymorph\Platform\Domain\Routing\Registrars\RouteNodeRegistrarInterface  $registrar  Регистратор
     */
    public function register(RouteNodeKind $kind, RouteNodeRegistrarInterface $registrar): void
    {
        $this->registrars[$kind->value] = $registrar;
    }

    /**
     * Создать регистратор для указанного типа узла.
     *
     * Возвращает зарегистрированный регистратор для указанного kind.
     * Если регистратор не найден, логирует предупреждение и возвращает null.
     *
     * @param  \Polymorph\Platform\Domain\Routing\Enums\RouteNodeKind  $kind  Тип узла
     * @return \Polymorph\Platform\Domain\Routing\Registrars\RouteNodeRegistrarInterface|null Регистратор или null, если не найден
     */
    public function create(RouteNodeKind $kind): ?RouteNodeRegistrarInterface
    {
        if (! isset($this->registrars[$kind->value])) {
            $this->logger->warning('routing.registrar_missing', [
                'kind' => $kind->value,
                'available_kinds' => array_keys($this->registrars),
            ]);

            return null;
        }

        return $this->registrars[$kind->value];
    }
}
