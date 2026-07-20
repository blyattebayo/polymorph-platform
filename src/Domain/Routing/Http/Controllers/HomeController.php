<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Polymorph\Platform\Domain\Routing\Http\Requests\HomeRequest;

/**
 * Контроллер для главной страницы (/).
 *
 * Рендерит дефолтный шаблон home.default.
 * Примечание: динамическая главная страница будет реализована через RouteNode
 * после внедрения иерархической маршрутизации.
 */
final class HomeController
{
    /**
     * @param  Factory  $view  Фабрика представлений
     */
    public function __construct(
        private readonly ViewFactory $view,
    ) {}

    /**
     * Обработать запрос к главной странице.
     *
     * @param  HomeRequest  $request  Запрос
     * @return View Представление
     */
    public function __invoke(HomeRequest $request): View
    {
        return $this->view->make('home.default');
    }
}
