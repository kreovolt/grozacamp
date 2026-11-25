<?php
declare(strict_types=1);

namespace Groza\Content;

/** Меню сайта из config/nav.php. */
final class Nav
{
    /** @var array<string,list<array<string,mixed>>>|null */
    private static ?array $config = null;

    /** @return list<array{label:string, href:string, kind:'button'|'link'|'dropdown', children?:list<array{label:string, href:string, children?:list<array{label:string, href:string}>}>}> */
    public static function main(): array
    {
        return self::config()['main'];
    }

    /** Колонка «Лагерю» в подвале. @return list<array{label:string, href:string}> */
    public static function footer(): array
    {
        return self::config()['footer'];
    }

    /** Политика и правила в нижней строке подвала. @return list<array{label:string, href:string}> */
    public static function legal(): array
    {
        return self::config()['legal'];
    }

    /** Пункт меню подсвечен: это его страница или страница внутри его раздела. Ссылки на якоря не подсвечиваются. */
    public static function isActive(string $href, string $currentPath): bool
    {
        if (str_contains($href, '#')) {
            return false;
        }
        if ($href === '/') {
            return $currentPath === '/';
        }
        if ($href === '/booking/' && str_starts_with($currentPath, '/program/')) {
            return true;
        }

        return str_starts_with($currentPath, $href);
    }

    /** @return array<string,list<array<string,mixed>>> */
    private static function config(): array
    {
        return self::$config ??= require dirname(__DIR__, 2) . '/config/nav.php';
    }
}
