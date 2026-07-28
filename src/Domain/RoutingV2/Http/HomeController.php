<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RoutingV2\Http;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;

/**
 * Главная страница (/).
 */
final class HomeController
{
    public function __construct(
        private readonly ViewFactory $view,
    ) {}

    public function __invoke(): View
    {
        return $this->view->make('home.default');
    }
}
