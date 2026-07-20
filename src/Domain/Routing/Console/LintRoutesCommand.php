<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Console;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

final class LintRoutesCommand extends Command
{
    protected $signature = 'routing:lint {--json : Output violations as JSON}';

    protected $description = 'Validate registered routes for duplicate names and URI/method collisions';

    public function handle(): int
    {
        $routes = RouteFacade::getRoutes()->getRoutes();

        $duplicateNames = $this->findDuplicateNames($routes);
        $duplicateSignatures = $this->findDuplicateSignatures($routes);

        if ($duplicateNames === [] && $duplicateSignatures === []) {
            $this->line('Routing lint passed.');

            return self::SUCCESS;
        }

        $payload = [
            'duplicate_names' => $duplicateNames,
            'duplicate_signatures' => $duplicateSignatures,
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } else {
            $this->error('Routing lint failed.');

            if ($duplicateNames !== []) {
                $this->line('Duplicate route names:');
                foreach ($duplicateNames as $row) {
                    $this->line(sprintf('- %s (%s)', $row['name'], implode(', ', $row['uris'])));
                }
            }

            if ($duplicateSignatures !== []) {
                $this->line('Duplicate route signatures (domain + method + uri):');
                foreach ($duplicateSignatures as $row) {
                    $this->line(sprintf('- %s %s%s (%s)', $row['method'], $row['domain'] !== '' ? $row['domain'] : '<any-domain>', $row['uri'], implode(', ', $row['names'])));
                }
            }
        }

        return self::FAILURE;
    }

    /**
     * @param  array<int, Route>  $routes
     * @return array<int, array{name:string, uris:array<int, string>}>
     */
    private function findDuplicateNames(array $routes): array
    {
        $byName = [];

        foreach ($routes as $route) {
            $name = (string) ($route->getName() ?? '');
            if ($name === '') {
                continue;
            }

            $domain = (string) ($route->domain() ?? '');
            $uri = '/'.ltrim($route->uri(), '/');
            $label = ($domain !== '' ? $domain : '<any-domain>').$uri;

            if (! isset($byName[$name])) {
                $byName[$name] = [];
            }
            $byName[$name][] = $label;
        }

        $duplicates = [];
        foreach ($byName as $name => $labels) {
            $unique = array_values(array_unique($labels));
            if (count($unique) > 1) {
                $duplicates[] = [
                    'name' => $name,
                    'uris' => $unique,
                ];
            }
        }

        return $duplicates;
    }

    /**
     * @param  array<int, Route>  $routes
     * @return array<int, array{domain:string, method:string, uri:string, names:array<int, string>}>
     */
    private function findDuplicateSignatures(array $routes): array
    {
        $bySignature = [];

        foreach ($routes as $route) {
            $domain = (string) ($route->domain() ?? '');
            $uri = '/'.ltrim($route->uri(), '/');
            $name = (string) ($route->getName() ?? '<unnamed>');

            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }

                $key = $domain.'|'.$method.'|'.$uri;
                if (! isset($bySignature[$key])) {
                    $bySignature[$key] = [
                        'domain' => $domain,
                        'method' => $method,
                        'uri' => $uri,
                        'names' => [],
                    ];
                }
                $bySignature[$key]['names'][] = $name;
            }
        }

        $duplicates = [];
        foreach ($bySignature as $signatureGroup) {
            $uniqueNames = array_values(array_unique($signatureGroup['names']));
            if (count($uniqueNames) > 1) {
                $duplicates[] = [
                    'domain' => $signatureGroup['domain'],
                    'method' => $signatureGroup['method'],
                    'uri' => $signatureGroup['uri'],
                    'names' => $uniqueNames,
                ];
            }
        }

        return $duplicates;
    }
}
