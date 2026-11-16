<?php

declare(strict_types=1);

namespace App;

use App\Controller\AppController;
use RuntimeException;

/** Matches the method and the path of a request with the routes in public/index.php and runs the action. */
final class Routing
{
    /** @var list<array{method: string, pattern: string, controller: class-string<AppController>, action: string}> */
    private array $routes = [];

    public function get(string $path, string $controller, string $action): void
    {
        $this->add('GET', $path, $controller, $action);
    }

    public function post(string $path, string $controller, string $action): void
    {
        $this->add('POST', $path, $controller, $action);
    }

    /**
     * Runs the action of the first route that matches. Returns false when none does, so the caller can answer 404.
     * A HEAD request is answered like GET; the server sends only its headers.
     */
    public function dispatch(string $method, string $uri): bool
    {
        $method = $method === 'HEAD' ? 'GET' : $method;
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method || !preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            // the named parameters only, and digits as numbers, so an {id} reaches the action as an int
            $parameters = array_map(
                fn(string $value): int|string => ctype_digit($value) ? (int) $value : $value,
                array_filter($matches, fn(int|string $key): bool => is_string($key), ARRAY_FILTER_USE_KEY),
            );

            $controller = new $route['controller']();

            if (!$controller instanceof AppController) {
                throw new RuntimeException("{$route['controller']} is not a controller");
            }

            $controller->{$route['action']}(...$parameters);

            return true;
        }

        return false;
    }

    private function add(string $method, string $path, string $controller, string $action): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $this->createPattern($path),
            'controller' => $controller,
            'action' => $action,
        ];
    }

    /**
     * Turns a path into a regular expression: the plain parts stay as they are, and a part in braces,
     * such as {id}, becomes a named parameter of digits only, so /app/logs/abc does not match /app/logs/{id}.
     */
    private function createPattern(string $path): string
    {
        $parts = array_map(
            fn(string $part): string => str_starts_with($part, '{') && str_ends_with($part, '}')
                ? '(?P<' . trim($part, '{}') . '>\d+)'
                : preg_quote($part, '~'),
            explode('/', $path),
        );

        // D: the end of the path is its real end, without room for a trailing newline
        return '~^' . implode('/', $parts) . '$~D';
    }
}
