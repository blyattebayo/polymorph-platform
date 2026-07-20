<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Http;

use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\ControllerDispatcher;
use Illuminate\Routing\Route;
use Polymorph\Sdk\Http\Reply;

/**
 * ControllerDispatcher ядра, который рендерит нейтральный SDK {@see Reply},
 * возвращённый контроллером расширения, в HTTP-ответ через {@see ReplyRenderer}.
 *
 * Это единственная глобальная точка (биндинг контракта ControllerDispatcher),
 * срабатывающая ТОЛЬКО когда возврат — Reply (контроллеры ядра возвращают
 * Response/массив и не затрагиваются). Так SDK остаётся framework-neutral
 * (Reply не зависит от Illuminate), а Laravel prepareResponse не падает на
 * «голом» Reply, потому что до него значение уже превращено в JsonResponse.
 */
final class ReplyAwareControllerDispatcher extends ControllerDispatcher
{
    public function __construct(
        Container $container,
        private readonly ReplyRenderer $renderer,
    ) {
        parent::__construct($container);
    }

    /**
     * @param  Route  $route
     * @param  object  $controller
     * @param  string  $method
     */
    public function dispatch($route, $controller, $method): mixed
    {
        $result = parent::dispatch($route, $controller, $method);

        return $result instanceof Reply ? $this->renderer->render($result) : $result;
    }
}
