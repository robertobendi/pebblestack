<?php

declare(strict_types=1);

namespace Pebblestack\Core;

/**
 * Tiny pattern router. Patterns use {name} placeholders that match a path
 * segment ([^/]+). Handlers are callables taking (Request) and returning
 * a Response. First registered match wins, so register specific routes
 * before catch-alls.
 */
final class Router
{
    /** @var array<int,array{method:string,regex:string,params:array<int,string>,handler:callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): self
    {
        return $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): self
    {
        return $this->add('POST', $pattern, $handler);
    }

    public function any(string $pattern, callable $handler): self
    {
        return $this->add('*', $pattern, $handler);
    }

    public function add(string $method, string $pattern, callable $handler): self
    {
        $params = [];
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            function ($m) use (&$params) {
                $params[] = $m[1];
                return '([^/]+)';
            },
            $pattern,
        );
        $regex = '#^' . $regex . '$#';
        $this->routes[] = [
            'method'  => $method,
            'regex'   => $regex,
            'params'  => $params,
            'handler' => $handler,
        ];
        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();
        foreach ($this->routes as $route) {
            if ($route['method'] !== '*' && $route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['regex'], $path, $matches)) {
                array_shift($matches);
                $params = [];
                foreach ($route['params'] as $i => $name) {
                    $params[$name] = urldecode($matches[$i]);
                }
                $request->params = $params;
                $result = ($route['handler'])($request);
                if (!$result instanceof Response) {
                    throw new \RuntimeException('Route handler must return a Response.');
                }
                return $result;
            }
        }
        return Response::notFound();
    }
}
