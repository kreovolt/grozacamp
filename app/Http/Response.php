<?php
declare(strict_types=1);

namespace Groza\Http;

final class Response
{
    /** @var array<string,string> */
    private array $headers = [];
    private ?string $file = null;
    private bool $deleteFile = false;

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

    /**
     * Ответ-файл: отдаётся потоком по кусочкам (память не растёт с размером файла). `$deleteAfter` — убрать файл после отправки
     * (и при обрыве соединения, на выходе из скрипта). `body()` читает файл целиком — только для тестов.
     */
    public static function file(string $path, string $contentType, bool $deleteAfter = false): Response
    {
        $r = (new Response(200, ''))->withHeader('Content-Type', $contentType)->withHeader('Content-Length', (string) filesize($path));
        $r->file = $path;
        $r->deleteFile = $deleteAfter;

        return $r;
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
        return $this->file !== null ? (string) file_get_contents($this->file) : $this->body;
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
        if ($this->file === null) {
            echo $this->body;

            return;
        }
        $file = $this->file;
        if ($this->deleteFile) {
            register_shutdown_function(static function () use ($file): void {
                @unlink($file);
            });
        }
        ignore_user_abort(true);
        while (PHP_SAPI !== 'cli' && ob_get_level() > 0) {
            ob_end_clean();
        }
        $in = fopen($file, 'rb');
        if ($in !== false) {
            while (!feof($in) && !connection_aborted() && ($chunk = fread($in, 65536)) !== false) {
                echo $chunk;
                flush();
            }
            fclose($in);
        }
        if ($this->deleteFile) {
            @unlink($file);
        }
    }
}
