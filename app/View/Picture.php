<?php
declare(strict_types=1);

/**
 * Адаптивные фото: дескриптор из public/assets/img/manifest.json → <picture> с avif/webp/jpg.
 * Дескриптор: {src, widths, formats, w, h[, position]}; файлы лежат как {src}-{width}.{format}.
 */

namespace Groza\View;

use InvalidArgumentException;

/**
 * Дескриптор фото по ключу из манифеста (создаётся `npm run photos`).
 *
 * @return array{src:string,widths:list<int>,formats:list<string>,w:int,h:int,position?:string}
 */
function photo(string $key): array
{
    static $manifest = null;
    if ($manifest === null) {
        $manifest = [];
        foreach ([dirname(__DIR__, 2) . '/public/assets/img/manifest.json', dirname(__DIR__, 2) . '/assets/img/manifest.json'] as $file) {
            if (is_file($file)) {
                $data = json_decode((string) file_get_contents($file), true);
                $manifest = is_array($data) ? $data : [];
                break;
            }
        }
    }
    if (!isset($manifest[$key]) || !is_array($manifest[$key])) {
        throw new InvalidArgumentException("Неизвестное фото: {$key}");
    }

    return $manifest[$key];
}

/**
 * Значение «изображение» из данных: ключ манифеста → дескриптор, массив-дескриптор — как есть, null → null.
 *
 * @param string|array<string,mixed>|null $value
 * @return array<string,mixed>|null
 */
function image(string|array|null $value): ?array
{
    if ($value === null) {
        return null;
    }

    return is_string($value) ? photo($value) : $value;
}

/**
 * <picture> с источниками по форматам (лучший первым) и запасным jpg.
 *
 * @param array{src:string,widths:list<int>,formats:list<string>,w:int,h:int} $img
 */
function picture(array $img, string $alt, string $sizes = '100vw', bool $eager = false, string $class = ''): string
{
    $types = ['avif' => 'image/avif', 'webp' => 'image/webp', 'jpg' => 'image/jpeg'];
    $widths = array_values($img['widths']);
    $srcset = static function (string $format) use ($img, $widths): string {
        return implode(', ', array_map(static fn (int $w): string => e("{$img['src']}-{$w}.{$format}") . " {$w}w", $widths));
    };

    $html = '<picture>';
    foreach (['avif', 'webp'] as $format) {
        if (in_array($format, $img['formats'], true)) {
            $html .= '<source type="' . $types[$format] . '" srcset="' . $srcset($format) . '" sizes="' . e($sizes) . '">';
        }
    }
    $fallback = $widths[min(1, count($widths) - 1)];
    $html .= '<img src="' . e("{$img['src']}-{$fallback}.jpg") . '" srcset="' . $srcset('jpg') . '" sizes="' . e($sizes) . '"'
        . ' width="' . (int) $img['w'] . '" height="' . (int) $img['h'] . '"'
        . ($class !== '' ? ' class="' . e($class) . '"' : '')
        . ($eager ? ' loading="eager" fetchpriority="high"' : ' loading="lazy"')
        . ' decoding="async" alt="' . e($alt) . '">';

    return $html . '</picture>';
}
