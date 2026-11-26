<?php
declare(strict_types=1);

namespace Groza\Support;

/**
 * Раскладка сайта на хостинге — та же проверка, что в public/index.php, asset() и photo():
 *  - split:  корень сайта — отдельная папка public/ рядом с app/ (public/index.php есть);
 *  - single: корень сайта — сама папка проекта (index.php лежит рядом с app/, папки public/ нет).
 * Отсюда — единственный источник папки загрузок: её адрес на сайте всегда /uploads/.
 */
final class Layout
{
    public static function isSplit(string $root): bool
    {
        return is_file(rtrim($root, '/\\') . '/public/index.php');
    }

    /** Папка, которую веб-сервер отдаёт как корень сайта. */
    public static function webRoot(string $root): string
    {
        $root = rtrim($root, '/\\');

        return self::isSplit($root) ? $root . '/public' : $root;
    }

    /** Папка загрузок (отдаётся по адресу /uploads/). */
    public static function uploadsDir(string $root): string
    {
        return self::webRoot($root) . '/uploads';
    }
}
