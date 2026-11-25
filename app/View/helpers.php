<?php
declare(strict_types=1);

/**
 * Помощники шаблонов. Подключаются Composer'ом (autoload.files), вызываются как
 * \Groza\View\e(...) или после `use function Groza\View\e;`.
 */

namespace Groza\View;

/** Экранирование для HTML-текста и значений атрибутов. */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Значение обязательного поля: пустое → «Информация обновляется» (спека §7.6), иначе экранированный текст. */
function pending(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '<span class="pending">Информация обновляется</span>';
    }

    return e($value);
}

/** Путь к собранному файлу с версией из public/assets/manifest.json: /assets/css/app.css?v=1a2b3c4d5e. */
function asset(string $path): string
{
    static $manifest = null;
    if ($manifest === null) {
        $manifest = [];
        // Корень сайта — либо отдельная папка public/, либо общая папка с app/ (см. public/index.php)
        foreach ([dirname(__DIR__, 2) . '/public/assets/manifest.json', dirname(__DIR__, 2) . '/assets/manifest.json'] as $file) {
            if (is_file($file)) {
                $data = json_decode((string) file_get_contents($file), true);
                $manifest = is_array($data) ? $data : [];
                break;
            }
        }
    }
    $hash = $manifest[$path] ?? null;

    return is_string($hash) && $hash !== '' ? $path . '?v=' . $hash : $path;
}

/** Иконка из спрайта /assets/icons.svg (только оформление — подпись даёт текст рядом или aria-label у ссылки). */
function icon(string $name, string $class = ''): string
{
    return '<svg class="' . e(trim('icon ' . $class)) . '" aria-hidden="true" focusable="false">'
        . '<use href="' . e(asset('/assets/icons.svg')) . '#i-' . e($name) . '"></use></svg>';
}

/** Ссылка для звонка: tel:+7XXXXXXXXXX (из любого написания номера оставляет цифры и «+»). */
function tel(string $e164): string
{
    return 'tel:' . preg_replace('/[^\d+]/', '', $e164);
}

/**
 * Текст с оформлением для вывода на публичной странице: ещё раз через Sanitizer (в JSON могло оказаться что угодно).
 * Результат безопасен для вставки в HTML без экранирования. Повтор того же текста за запрос — из кэша.
 */
function richtext(?string $html): string
{
    static $cache = [];
    $html = (string) $html;
    if ($html === '') {
        return '';
    }
    $key = md5($html);
    if (!isset($cache[$key])) {
        if (count($cache) > 200) {
            $cache = [];
        }
        $cache[$key] = \Groza\Support\Sanitizer::clean($html);
    }

    return $cache[$key];
}

/** Адрес для href из настроек: только http/https с адресом сайта, иначе пустая строка (дальше e()). */
function safe_url(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '' || preg_match('#^https?://[^\s"\'<>\\\\]+$#i', $url) !== 1) {
        return '';
    }
    $host = parse_url($url, PHP_URL_HOST);

    return is_string($host) && $host !== '' ? $url : '';
}
