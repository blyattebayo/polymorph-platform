<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Http\Controllers;

use Polymorph\Platform\Domain\Routing\Http\Requests\HomeRequest;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;

/**
 * Контроллер для главной страницы (/).
 *
 * Рендерит дефолтный шаблон home.default.
 * Примечание: динамическая главная страница будет реализована через RouteNode
 * после внедрения иерархической маршрутизации.
 *
 * @package Polymorph\Platform\Http\Controllers
 */
final class HomeController
{
    /**
     * @param \Illuminate\Contracts\View\Factory $view Фабрика представлений
     */
    public function __construct(
        private readonly ViewFactory $view,
    ) {
    }

    /**
     * Обработать запрос к главной странице.
     *
        * @param \Polymorph\Platform\Domain\Routing\Http\Requests\HomeRequest $request Запрос
     * @return \Illuminate\Contracts\View\View Представление
     */
    public function __invoke(HomeRequest $request): View
    {
        return $this->view->make('home.default');
    }
}

