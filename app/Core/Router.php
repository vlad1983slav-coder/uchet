<?php

declare(strict_types=1);

namespace Uchet\Core;

/**
 * Простой роутер: URI с параметрами {id} → 'Контроллер@метод' | [Класс, 'метод'] | callable.
 * Поддерживает группы (prefix + middleware) и именованные маршруты для url().
 *
 * Контроллеры ищутся в Uchet\Controllers\, middleware — в Uchet\Middleware\.
 */
final class Router
{
    /** @var array<int, array{methods: string[], uri: string, action: callable|array|string, middleware: string[], name: ?string}> */
    private static array $routes = [];

    /** @var array<string, array{uri: string}> */
    private static array $named = [];

    /** @var array<int, array{prefix?: string, middleware?: string[]}> */
    private static array $groupStack = [];

    public static function get(string $uri, callable|array|string $action, array $middleware = [], ?string $name = null): void
    {
        self::add(['GET', 'HEAD'], $uri, $action, $middleware, $name);
    }

    public static function post(string $uri, callable|array|string $action, array $middleware = [], ?string $name = null): void
    {
        self::add(['POST'], $uri, $action, $middleware, $name);
    }

    public static function put(string $uri, callable|array|string $action, array $middleware = [], ?string $name = null): void
    {
        self::add(['PUT'], $uri, $action, $middleware, $name);
    }

    public static function patch(string $uri, callable|array|string $action, array $middleware = [], ?string $name = null): void
    {
        self::add(['PATCH'], $uri, $action, $middleware, $name);
    }

    public static function delete(string $uri, callable|array|string $action, array $middleware = [], ?string $name = null): void
    {
        self::add(['DELETE'], $uri, $action, $middleware, $name);
    }

    public static function any(string $uri, callable|array|string $action, array $middleware = [], ?string $name = null): void
    {
        self::add(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], $uri, $action, $middleware, $name);
    }

    public static function add(array $methods, string $uri, callable|array|string $action, array $middleware = [], ?string $name = null): void
    {
        $group = end(self::$groupStack) ?: [];
        $prefix = (string) ($group['prefix'] ?? '');
        $uri = '/' . trim($prefix . '/' . trim($uri, '/'), '/');
        $uri = $uri === '/' ? '/' : $uri;
        $route = [
            'methods' => array_map('strtoupper', $methods),
            'uri' => $uri,
            'action' => $action,
            'middleware' => array_merge((array) ($group['middleware'] ?? []), $middleware),
            'name' => $name,
        ];
        self::$routes[] = $route;
        if ($name !== null) {
            self::$named[$name] = ['uri' => $uri];
        }
    }

    /** Группа маршрутов: ['prefix' => '/documents', 'middleware' => ['auth']]. */
    public static function group(array $attributes, callable $callback): void
    {
        self::$groupStack[] = $attributes;
        $callback();
        array_pop(self::$groupStack);
    }

    public static function load(string $routesFile): void
    {
        require $routesFile;
    }

    public static function dispatch(): void
    {
        $method = Request::method();
        $path = Request::path();
        $allowedMethods = [];

        foreach (self::$routes as $route) {
            $params = self::match($route['uri'], $path);
            if ($params === null) {
                continue;
            }
            if (!in_array($method, $route['methods'], true)) {
                $allowedMethods = array_merge($allowedMethods, $route['methods']);
                continue;
            }
            foreach ($route['middleware'] as $middleware) {
                self::runMiddleware($middleware);
            }
            self::runAction($route['action'], $params);

            return;
        }

        if ($allowedMethods !== []) {
            header('Allow: ' . implode(', ', array_unique($allowedMethods)));
            throw new HttpException(405, 'Метод ' . $method . ' не поддерживается для этого адреса');
        }
        throw new NotFoundException('Не найден маршрут: ' . $method . ' ' . $path);
    }

    /** @return array<string, string>|null — параметры маршрута или null, если не совпал */
    private static function match(string $uri, string $path): ?array
    {
        if ($uri === $path) {
            return [];
        }
        $regex = '#^' . preg_replace('#\{([A-Za-z_][A-Za-z0-9_]*)\}#', '(?P<$1>[^/]+)', $uri) . '$#';
        if (preg_match($regex, $path, $matches) === 1) {
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }

        return null;
    }

    private static function runMiddleware(string $middleware): void
    {
        $class = str_starts_with($middleware, '\\') ? $middleware : 'Uchet\\Middleware\\' . $middleware;
        if (!class_exists($class)) {
            throw new \RuntimeException("Middleware не найден: {$class}");
        }
        $instance = new $class();
        if (!$instance instanceof \Uchet\Middleware\Middleware) {
            throw new \RuntimeException("{$class} должен реализовывать Uchet\\Middleware\\Middleware");
        }
        $instance->handle();
    }

    private static function runAction(callable|array|string $action, array $params): void
    {
        $callable = self::resolveAction($action);
        $result = $callable(...array_values($params));
        if (is_string($result)) {
            echo $result;
        }
    }

    private static function resolveAction(callable|array|string $action): callable
    {
        if (is_string($action)) {
            [$class, $method] = array_pad(explode('@', $action, 2), 2, 'index');
            $class = str_contains($class, '\\') ? $class : 'Uchet\\Controllers\\' . $class;

            return [self::makeController($class), $method];
        }
        if (is_array($action) && isset($action[0]) && is_string($action[0])) {
            $class = str_contains($action[0], '\\') ? $action[0] : 'Uchet\\Controllers\\' . $action[0];

            return [self::makeController($class), (string) ($action[1] ?? 'index')];
        }

        return $action;
    }

    private static function makeController(string $class): object
    {
        if (!class_exists($class)) {
            throw new \RuntimeException("Контроллер не найден: {$class}");
        }

        return new $class();
    }

    /** URL именованного маршрута с подстановкой параметров. */
    public static function url(string $name, array $params = []): string
    {
        if (!isset(self::$named[$name])) {
            throw new \InvalidArgumentException("Маршрут не найден: {$name}");
        }
        $uri = self::$named[$name]['uri'];
        foreach ($params as $key => $value) {
            $replaced = preg_replace(
                '#\{' . preg_quote((string) $key, '#') . '\}#',
                rawurlencode((string) $value),
                $uri,
                1,
                $count
            );
            if ($count === 0) {
                throw new \InvalidArgumentException("Лишний параметр \"{$key}\" для маршрута {$name}");
            }
            $uri = (string) $replaced;
        }
        if (preg_match('#\{([A-Za-z_][A-Za-z0-9_]*)\}#', $uri, $m) === 1) {
            throw new \InvalidArgumentException("Не заполнен параметр \"{$m[1]}\" для маршрута {$name}");
        }

        return rtrim((string) config('app.base_url', ''), '/') . $uri;
    }

    /** @return array<int, array{methods: string[], uri: string, name: ?string}> — для отладки */
    public static function routes(): array
    {
        return array_map(
            static fn (array $route): array => [
                'methods' => $route['methods'],
                'uri' => $route['uri'],
                'name' => $route['name'],
            ],
            self::$routes
        );
    }
}
