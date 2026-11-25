<?php
declare(strict_types=1);

namespace Groza\Http;

final class Response
{
    /** @var array<string,string> */
    private array $headers = [];

    private function __construct(private int $status, private string $body)
    {
    }

    public static function html(string $body, int $status = 200): Response
    {
        return (new Response($status, $body))->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public static function redirect(string $url, int $status = 302): Response
    {
        return (new Response($status, ''))->withHeader('Location', $url);
    }

    /** @param array<mixed> $data */
    public static function json(array $data, int $status = 200): Response
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return (new Response($status, $body))->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public function withHeader(string $n, string $v): Response
    {
        $copy = clone $this;
        foreach (array_keys($copy->headers) as $existing) {
            if (strcasecmp($existing, $n) === 0) {
                unset($copy->headers[$existing]);
            }
        }
        $copy->headers[$n] = $v;
        return $copy;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function header(string $n): ?string
    {
        foreach ($this->headers as $name => $value) {
            if (strcasecmp($name, $n) === 0) {
                return $value;
            }
        }
        return null;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
