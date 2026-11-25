<?php
declare(strict_types=1);

namespace Groza\Http;

final class Request
{
    /** @var array<string,string> имена заголовков в нижнем регистре */
    private array $headers = [];

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $post
     * @param array<string,string> $headers
     * @param array<string,array<string,mixed>> $files
     */
    private function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $post,
        array $headers,
        private array $files,
        private string $ip,
    ) {
        foreach ($headers as $name => $value) {
            $this->headers[strtolower((string) $name)] = (string) $value;
        }
    }

    public static function fromGlobals(): Request
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($key, 5))] = (string) $value;
            }
        }
        foreach (['CONTENT_TYPE' => 'content-type', 'CONTENT_LENGTH' => 'content-length'] as $key => $name) {
            if (isset($_SERVER[$key])) {
                $headers[$name] = (string) $_SERVER[$key];
            }
        }
        // Basic-авторизация: PHP кладёт её в PHP_AUTH_*, а под CGI/FastCGI заголовок приходит через переменную окружения
        if (!isset($headers['AUTHORIZATION'])) {
            $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
            if (is_string($auth) && $auth !== '') {
                $headers['AUTHORIZATION'] = $auth;
            } elseif (isset($_SERVER['PHP_AUTH_USER'])) {
                $headers['AUTHORIZATION'] = 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . ($_SERVER['PHP_AUTH_PW'] ?? ''));
            }
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = rawurldecode((string) (parse_url($uri, PHP_URL_PATH) ?? '/'));

        return new Request(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $path === '' ? '/' : $path,
            $_GET,
            $_POST,
            $headers,
            $_FILES,
            (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
        );
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $post
     * @param array<string,string> $headers
     * @param array<string,array<string,mixed>> $files
     */
    public static function create(
        string $method,
        string $path,
        array $query = [],
        array $post = [],
        array $headers = [],
        array $files = [],
        string $ip = '127.0.0.1',
    ): Request {
        return new Request(strtoupper($method), $path, $query, $post, $headers, $files, $ip);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $k, ?string $default = null): ?string
    {
        $v = $this->query[$k] ?? null;
        return is_string($v) ? $v : $default;
    }

    public function queryString(): string
    {
        return http_build_query($this->query);
    }

    public function post(string $k, $default = null)
    {
        return $this->post[$k] ?? $default;
    }

    /** @return array<string,mixed> */
    public function allPost(): array
    {
        return $this->post;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** Хост запроса в нижнем регистре (с портом, если он указан); пусто, если заголовка нет. */
    public function host(): string
    {
        return strtolower(trim((string) $this->header('Host')));
    }

    public function ip(): string
    {
        return $this->ip;
    }

    /** Все загруженные файлы запроса по именам полей. @return array<string,array<string,mixed>> */
    public function files(): array
    {
        return $this->files;
    }

    /** @return array<string,mixed>|null */
    public function file(string $k): ?array
    {
        return $this->files[$k] ?? null;
    }

    public function wantsJson(): bool
    {
        return str_contains((string) $this->header('Accept'), 'application/json');
    }
}
