<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /**
     * @var array<int, array{method:string,path:string,regex:string,params:array<int,string>,handler:callable|array{object,string},middlewares:array<int, MiddlewareInterface>}>
     */
    private array $routes = [];

    /**
     * @param callable|array{object,string} $handler
     * @param array<int, MiddlewareInterface> $middlewares
     */
    public function add(string $method, string $path, callable|array $handler, array $middlewares = []): void
    {
        [$regex, $params] = $this->compilePath($path);
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'regex' => $regex,
            'params' => $params,
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }

            if (!preg_match($route['regex'], $request->path(), $matches)) {
                continue;
            }

            foreach ($route['params'] as $paramName) {
                $request->setAttribute($paramName, $matches[$paramName] ?? null);
            }

            $kernel = $this->buildKernel($route['handler'], $route['middlewares']);
            return $kernel($request);
        }

        if (str_starts_with($request->path(), '/api/')) {
            return ApiResponder::error(
                message: 'Not Found',
                errorCode: 'NOT_FOUND',
                status: 404,
                request: $request
            );
        }

        return Response::html('<h1>404 Not Found</h1>', 404);
    }

    /**
     * @param callable|array{object,string} $handler
     * @param array<int, MiddlewareInterface> $middlewares
     * @return callable(Request): Response
     */
    private function buildKernel(callable|array $handler, array $middlewares): callable
    {
        $core = function (Request $request) use ($handler): Response {
            if (is_callable($handler)) {
                return $handler($request);
            }

            return $handler[0]->{$handler[1]}($request);
        };

        $pipeline = array_reduce(
            array_reverse($middlewares),
            /**
             * @param callable(Request): Response $next
             */
            static function (callable $next, MiddlewareInterface $middleware): callable {
                return static fn (Request $request): Response => $middleware->handle($request, $next);
            },
            $core
        );

        return static fn (Request $request): Response => $pipeline($request);
    }

    /**
     * @return array{0:string,1:array<int,string>}
     */
    private function compilePath(string $path): array
    {
        $params = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function (array $matches) use (&$params): string {
            $params[] = $matches[1];
            return '(?<' . $matches[1] . '>[^/]+)';
        }, $path) ?? $path;

        return ['#^' . $regex . '$#', $params];
    }
}
