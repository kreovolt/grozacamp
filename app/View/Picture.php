<?php
declare(strict_types=1);

/**
 * Адаптивные фото: дескриптор из public/assets/img/manifest.json → <picture> с avif/webp/jpg.
 * Дескриптор: {src, widths, formats, w, h[, position][, alt]}; файлы лежат как {src}-{width}.{format}.
 * position — точка кадра для object-position, alt — описание снимка по-русски (подпись по умолчанию);
 * оба приходят из config/photos.json через манифест. Поле og из photos.json в манифест не попадает —
 * это кроп OG-картинки героя, его читает только scripts/optimize-photos.mjs.
 */

namespace Groza\View;

use InvalidArgumentException;
use RuntimeException;

/**
 * Читает манифест фото из первого существующего файла списка. Нет файла или он испорчен — это сбой сборки, а не «неизвестное фото»:
 * поэтому отдельное исключение с понятным текстом (запустите `npm run photos`).
 *
 * @param list<string> $files
 * @return array<string,mixed>
 * @throws RuntimeException
 */
function load_photo_manifest(array $files): array
{
    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }
        $raw = file_get_contents($file);
        $data = $raw === false ? null : json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('Манифест фото повреждён: ' . basename(dirname($file)) . '/' . basename($file) . ' (пересоберите: npm run photos)');
        }

        return $data;
    }

    throw new RuntimeException('Манифест фото не найден (assets/img/manifest.json). Соберите фото: npm run photos');
}

/**
 * Дескриптор фото по ключу из манифеста (создаётся `npm run photos`).
 *
 * @return array{src:string,widths:list<int>,formats:list<string>,w:int,h:int,position?:string,alt?:string}
 */
function photo(string $key): array
{
    static $manifest = null;
    $manifest ??= load_photo_manifest([dirname(__DIR__, 2) . '/public/assets/img/manifest.json', dirname(__DIR__, 2) . '/assets/img/manifest.json']);
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
    if ($widths === []) {
        throw new InvalidArgumentException('У фото нет ни одного размера (widths пуст)');
    }
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
