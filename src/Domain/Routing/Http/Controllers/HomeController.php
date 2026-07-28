<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Http\Controllers;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;

/**
 * Контроллер для главной страницы (/).
 *
 * Рендерит дефолтный шаблон home.default.
 * Примечание: динамическая главная страница будет реализована через RouteNode
 * после внедрения иерархической маршрутизации.
 */
final class HomeController
{
    public function __construct(
        private readonly ViewFactory $view,
    ) {}

    /**
     * Обработать запрос к главной странице.
     */
    public function __invoke(): View
    {
        return $this->view->make('home.default');
    }
}
