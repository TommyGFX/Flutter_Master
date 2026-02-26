<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[strtoupper($method)][$path] = $handler;
    }

    public function dispatch(Request $request): void
    {
        $method = strtoupper($request->method());
        $path = $request->path();

        foreach ($this->routes[$method] ?? [] as $route => $handler) {
            $pattern = '#^' . preg_replace('#\{([^/]+)\}#', '([^/]+)', $route) . '$#';
            if (preg_match($pattern, $path, $matches) === 1) {
                array_shift($matches);
                $handler($request, ...$matches);
                return;
            }
        }

        Response::json(['error' => 'not_found'], 404);
    }
}
