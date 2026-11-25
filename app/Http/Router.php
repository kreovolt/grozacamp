<?php
declare(strict_types=1);

namespace Groza\Http;

final class Router
{
    /** @var list<array{0:string,1:string,2:callable}> метод, регулярное выражение, обработчик */
    private array $routes = [];

    /** @var array<string,string> */
    private array $redirects = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    /** Точные пути → цель, ответ 301 за один переход. @param array<string,string> $map */
    public function redirects(array $map): void
    {
        foreach ($map as $from => $to) {
            $this->redirects[$from] = $to;
        }
    }

    public function dispatch(Request $r): Response
    {
        $path = $r->path();
        $method = $r->method() === 'HEAD' ? 'GET' : $r->method();

        $target = $this->redirects[$path] ?? $this->redirects[$path . '/'] ?? null;
        if ($target !== null) {
            return Response::redirect($target, 301);
        }

        $allowed = [];
        foreach ($this->routes as [$m, $regex, $handler]) {
            if (preg_match($regex, $path, $found) !== 1) {
                continue;
            }
            if ($m !== $method) {
                $allowed[$m] = true;
                continue;
            }
            $params = array_filter($found, 'is_string', ARRAY_FILTER_USE_KEY);
            return $handler($r, $params);
        }

        if ($allowed !== []) {
            return Response::html('Method Not Allowed', 405)->withHeader('Allow', implode(', ', array_keys($allowed)));
        }

        if ($method === 'GET' && !str_ends_with($path, '/') && $this->matchesAny($path . '/')) {
            $query = $r->queryString();
            return Response::redirect($path . '/' . ($query !== '' ? '?' . $query : ''), 301);
        }

        return Response::html('Not Found', 404);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $regex = preg_replace_callback(
            '/\\\\\{([a-z]+)\\\\\}/',
            static fn (array $m): string => '(?P<' . $m[1] . '>[a-z0-9-]+)',
            preg_quote($pattern, '#')
        );
        $this->routes[] = [$method, '#^' . $regex . '$#', $handler];
    }

    private function matchesAny(string $path): bool
    {
        foreach ($this->routes as [, $regex]) {
            if (preg_match($regex, $path) === 1) {
                return true;
            }
        }
        return false;
    }
}
