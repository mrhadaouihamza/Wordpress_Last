<?php

declare(strict_types=1);

namespace Wordpresso\Core;

/**
 * Minimal router. Routes are "METHOD /path/{param}/suffix" patterns.
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, handler:array}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable|array $handler): void
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'regex' => '#^' . $regex . '/?$#',
            'handler' => $handler,
        ];
    }

    public function get(string $pattern, callable|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    /**
     * Match a request and invoke the handler.
     * Returns [handlerResult, params] or null when no route matches.
     */
    public function match(string $method, string $path): ?array
    {
        $method = strtoupper($method);
        $path = rtrim($path, '/') ?: '/';
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method && $route['method'] !== 'ANY') {
                continue;
            }
            if (preg_match($route['regex'], $path, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                return [$route['handler'], $params];
            }
        }
        return null;
    }
}
