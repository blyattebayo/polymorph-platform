<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RoutingV2\Console;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Проверка фактически зарегистрированных маршрутов.
 *
 * Ловит два класса ошибок, которые иначе проявляются молча:
 * - дубли имён: route() уведёт не туда, куда ждёт автор;
 * - совпадающие method+uri: RouteCollection пишет по ключу method+domain+uri,
 *   поэтому более поздняя регистрация ТИХО заменяет более раннюю. Порядком
 *   это не лечится и комментарием не документируется — только проверкой.
 */
final class LintRoutesCommand extends Command
{
    protected $signature = 'routing:lint {--json : Вывести нарушения в JSON}';

    protected $description = 'Validate registered routes for duplicate names and method+uri collisions';

    public function handle(): int
    {
        $routes = RouteFacade::getRoutes()->getRoutes();

        $violations = [
            'duplicate_names' => $this->duplicateNames($routes),
            'colliding_signatures' => $this->collidingSignatures($routes),
        ];

        if ($violations['duplicate_names'] === [] && $violations['colliding_signatures'] === []) {
            $this->info(sprintf('Routing lint passed: %d routes.', count($routes)));

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($violations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return self::FAILURE;
        }

        $this->error('Routing lint failed.');

        foreach ($violations['duplicate_names'] as $name => $uris) {
            $this->line(sprintf('  duplicate name %s: %s', $name, implode(', ', $uris)));
        }

        foreach ($violations['colliding_signatures'] as $signature => $names) {
            $this->line(sprintf('  colliding %s: %s', $signature, implode(', ', $names)));
        }

        return self::FAILURE;
    }

    /**
     * Одно имя на несколько разных URI.
     *
     * @param  array<int, Route>  $routes
     * @return array<string, list<string>>
     */
    private function duplicateNames(array $routes): array
    {
        $byName = [];

        foreach ($routes as $route) {
            $name = (string) ($route->getName() ?? '');

            if ($name === '') {
                continue;
            }

            $byName[$name][] = $this->uriOf($route);
        }

        return array_filter(
            array_map(static fn (array $uris): array => array_values(array_unique($uris)), $byName),
            static fn (array $uris): bool => count($uris) > 1,
        );
    }

    /**
     * Несколько маршрутов на один и тот же method+domain+uri.
     *
     * Считаем ПО КОЛИЧЕСТВУ маршрутов, а не по числу уникальных имён: два
     * безымянных дубля — самый опасный случай, и именно его прежняя проверка
     * пропускала, схлопывая оба в одно «<unnamed>».
     *
     * @param  array<int, Route>  $routes
     * @return array<string, list<string>>
     */
    private function collidingSignatures(array $routes): array
    {
        $bySignature = [];

        foreach ($routes as $route) {
            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }

                $bySignature[$method.' '.$this->uriOf($route)][] = (string) ($route->getName() ?? '<unnamed>');
            }
        }

        return array_filter($bySignature, static fn (array $names): bool => count($names) > 1);
    }

    private function uriOf(Route $route): string
    {
        $domain = (string) ($route->domain() ?? '');

        return ($domain !== '' ? $domain : '').'/'.ltrim($route->uri(), '/');
    }
}
