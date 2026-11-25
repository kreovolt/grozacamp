<?php
declare(strict_types=1);

namespace Groza\Http;

/**
 * Для встроенного сервера PHP (только разработка): отдавать ли файл из public/ как есть.
 * Файл должен реально лежать внутри каталога сайта (после realpath: «..», «%2e%2e» и ссылки наружу не проходят)
 * и не быть PHP-скриптом.
 */
final class StaticFiles
{
    public static function servable(string $docRoot, string $uriPath): bool
    {
        $path = rawurldecode($uriPath);
        if ($path === '' || $path === '/' || str_contains($path, "\0")) {
            return false;
        }
        $root = realpath($docRoot);
        $real = realpath($root . '/' . ltrim($path, '/\\'));
        if ($root === false || $real === false || !is_file($real)) {
            return false;
        }
        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $real = str_replace('\\', '/', $real);

        return str_starts_with($real, $root) && strtolower(pathinfo($real, PATHINFO_EXTENSION)) !== 'php';
    }
}
