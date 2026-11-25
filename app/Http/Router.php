<?php
declare(strict_types=1);

namespace Groza\Http;

final class Router
{
    /** @var list<array{0:string,1:string,2:callable}> метод, регулярное выражение, обработчик */
    private array $routes = [];

    /** @var array<string,string> */
    private array $redirects = [];

    /** @var list<string> */
    private array $gone = [];

    /** @var callable|null обработчик 404 (страница «не найдено»); без него — короткий текст */
    private $notFound = null;

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    /** Страница для неизвестных адресов: вызывается как обычный обработчик и должна вернуть ответ со статусом 404. */
    public function notFound(callable $handler): void
    {
        $this->notFound = $handler;
    }

    /** Точные пути → цель, ответ 301 за один переход. @param array<string,string> $map */
    public function redirects(array $map): void
    {
        foreach ($map as $from => $to) {
            $this->redirects[$from] = $to;
        }
    }

    /** Адреса старого сайта, которых нет: «/dir/» закрывает и всё внутри, «/file.php» — только сам файл. Отвечают 404 на любой метод. @param list<string> $paths */
    public function gone(array $paths): void
    {
        $this->gone = array_values(array_merge($this->gone, $paths));
    }

    public function dispatch(Request $r): Response
    {
        $path = $r->path();
        $method = $r->method() === 'HEAD' ? 'GET' : $r->method();

        if ($this->isGone($path)) {
            return $this->notFound !== null ? ($this->notFound)($r, []) : Response::html('Not Found', 404);
        }

        // Переадресации — только для чтения страниц; запись (POST и др.) идёт обычным маршрутом
        $target = $method === 'GET' ? ($this->redirects[$path] ?? $this->redirects[$path . '/'] ?? null) : null;
        if ($target !== null) {
            return Response::redirect(self::withQuery($target, $r->queryString()), 301);
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

        return $this->notFound !== null ? ($this->notFound)($r, []) : Response::html('Not Found', 404);
    }

    private function isGone(string $path): bool
    {
        $path = strtolower($path); // «/WP-Admin/» — тот же служебный адрес
        foreach ($this->gone as $entry) {
            $entry = strtolower($entry);
            if ($path === $entry || ($path . '/' === $entry) || (str_ends_with($entry, '/') && str_starts_with($path, $entry))) {
                return true;
            }
        }

        return false;
    }

    /** Строка запроса уходит в цель до «#якоря»: /faq/?a=1 → /info/?a=1#faq. */
    private static function withQuery(string $target, string $query): string
    {
        if ($query === '') {
            return $target;
        }
        $hash = '';
        if (($pos = strpos($target, '#')) !== false) {
            $hash = substr($target, $pos);
            $target = substr($target, 0, $pos);
        }

        return $target . (str_contains($target, '?') ? '&' : '?') . $query . $hash;
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $regex = preg_replace_callback(
            '/\\\\\{([a-z]+)\\\\\}/',
            static fn (array $m): string => '(?P<' . $m[1] . '>[a-z0-9-]+)',
            preg_quote($pattern, '#')
        );
        $this->routes[] = [$method, '#^' . $regex . '$#D', $handler];
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
