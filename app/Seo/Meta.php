<?php
declare(strict_types=1);

namespace Groza\Seo;

/** Заголовок, описание, канонический адрес и картинка страницы. Настройки — config/seo.php. */
final class Meta
{
    /** @var array<string,mixed>|null */
    private static ?array $config = null;

    /**
     * ctx: baseUrl, path (адрес страницы), title (название страницы без суффикса), description, image.
     *
     * @param array<string,mixed> $ctx
     * @return array{title:string, description:string, canonical:string, image:string}
     */
    public static function for(string $pageId, array $ctx = []): array
    {
        $cfg = self::config();
        $page = $cfg['pages'][$pageId] ?? [];
        $base = rtrim((string) ($ctx['baseUrl'] ?? ''), '/');

        $name = trim((string) ($ctx['title'] ?? '')) ?: (string) ($page['title'] ?? '');
        if ($pageId === 'home' && $name === '') {
            $title = (string) $cfg['homeTitle'];
        } else {
            $title = $name !== '' ? $name . $cfg['titleSuffix'] : (string) $cfg['defaultTitle'];
        }

        $description = trim((string) ($ctx['description'] ?? '')) ?: (string) ($page['description'] ?? $cfg['defaultDescription']);
        $path = (string) ($ctx['path'] ?? '/');
        $image = (string) (($ctx['image'] ?? '') ?: $cfg['image']);

        return [
            'title' => $title,
            'description' => self::trim($description, (int) $cfg['descriptionMax']),
            'canonical' => $base . ($path === '' ? '/' : $path),
            'image' => str_starts_with($image, '/') ? $base . $image : $image,
        ];
    }

    /** Пробелы схлопываются; длиннее лимита — обрезка по слову и «…» (вместе с ним не больше лимита). */
    private static function trim(string $text, int $max): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space > $max / 2) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, " ,;:—-.") . '…';
    }

    /** @return array<string,mixed> */
    private static function config(): array
    {
        return self::$config ??= require dirname(__DIR__, 2) . '/config/seo.php';
    }
}
