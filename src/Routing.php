<?php

declare(strict_types=1);

namespace App;

use App\Controller\AppController;

final class Routing
{
    private array $routes = [];

    public function get(string $path, string $controller, string $action): void
    {
        $this->add('GET', $path, $controller, $action);
    }

    public function post(string $path, string $controller, string $action): void
    {
        $this->add('POST', $path, $controller, $action);
    }

    private function add(
        string $method,
        string $path,
        string $controller,
        string $action,
    ): void {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $this->createPattern($path),
            'controller' => $controller,
            'action' => $action,
        ];
    }

	/* 
	Funkcja dzieli ścieżkę na fragmenty. Zwykłe fragmenty pozostawia bez zmian, 
	a fragment zapisany w klamrach, na przykład {id}, zamienia na nazwany 
	parametr przyjmujący cyfry. Następnie ponownie składa ścieżkę w wyrażenie regularne.
	*/
    private function createPattern(string $path): string
    {
        $parts = explode('/', $path);

        foreach ($parts as &$part) {
            if (str_starts_with($part, '{') && str_ends_with($part, '}')) {
                $parameterName = trim($part, '{}');
                $part = '(?P<' . $parameterName . '>\d+)';
                continue;
            }

            $part = preg_quote($part, '~');
        }
        unset($part);

        return '~^' . implode('/', $parts) . '$~';
    }

    public function dispatch(string $method, string $uri): bool
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            $parameters = array_filter(
                $matches,
                static fn (int|string $key): bool => is_string($key),
                ARRAY_FILTER_USE_KEY,
            );

            $parameters = array_map(
                static fn (string $value): int|string =>
                    ctype_digit($value) ? (int) $value : $value,
                $parameters,
            );

            $controllerClass = $route['controller'];
            $controller = new $controllerClass();

            if (!$controller instanceof AppController) {
                throw new \RuntimeException('Invalid controller');
            }

            $controller->{$route['action']}(...$parameters);

            return true;
        }

        return false;
    }
}