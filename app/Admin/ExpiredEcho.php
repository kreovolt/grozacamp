<?php
declare(strict_types=1);

namespace Groza\Admin;

use Groza\Admin\Controllers\SettingsController;
use Groza\Domain\Collections;
use Groza\Http\Request;
use Groza\Readiness\Readiness;

/**
 * Страница «сессия устарела» (419) для POST из форм админки: набранный текст не должен пропасть.
 * Из запроса берутся строковые поля формы и показываются обратно (на странице они экранируются);
 * токены, `_hash`, пароли и всё, что на них похоже, не возвращаются. Общий объём — не больше LIMIT байт.
 * Чужой сайт, отправивший форму в админку, свой текст на странице не увидит: если браузер назвал источник (Origin, иначе Referer),
 * он должен совпадать с адресом самой админки.
 */
final class ExpiredEcho
{
    public const LIMIT = 102400;
    private const MAX_DEPTH = 3;
    /** Страницы, где вводят пароль или выходят, текст не возвращают. */
    private const SKIP_PATHS = ['/admin/login/', '/admin/password/', '/admin/logout/'];

    /**
     * @return array{items:list<array{label:string,value:string}>, truncated:bool, next:?string}
     */
    public static function from(Request $r): array
    {
        $path = $r->path();
        $items = [];
        $left = self::LIMIT;
        $truncated = false;
        if (!in_array($path, self::SKIP_PATHS, true) && self::sameOrigin($r)) {
            foreach (self::flatten($r->allPost()) as $name => $value) {
                if ($value === '' || self::secret($name)) {
                    continue;
                }
                if ($left <= 0) {
                    $truncated = true;
                    break;
                }
                if (strlen($value) > $left) {
                    $value = mb_strcut($value, 0, $left, 'UTF-8');
                    $truncated = true;
                }
                $left -= strlen($value);
                $items[] = ['label' => mb_substr(self::label($path, $name), 0, 80), 'value' => self::isRichtext($path, $name) ? self::plain($value) : $value];
            }
        }

        return ['items' => $items, 'truncated' => $truncated, 'next' => self::next($path)];
    }

    /** Источник запроса (Origin, иначе Referer) — тот же хост, что у админки; браузер не назвал источник — считаем своим. */
    private static function sameOrigin(Request $r): bool
    {
        $from = $r->header('Origin');
        if ($from === null || $from === '' || $from === 'null') {
            $from = $r->header('Referer');
        }
        if ($from === null || $from === '') {
            return $r->header('Origin') !== 'null'; // «null» — песочница или чужой документ
        }
        $p = parse_url($from);
        $host = is_array($p) ? strtolower((string) ($p['host'] ?? '')) . (isset($p['port']) ? ':' . $p['port'] : '') : '';

        return $host !== '' && $host === $r->host();
    }

    /** @param array<mixed> $post @return array<string,string> «имя[вложенное]» → строка */
    private static function flatten(array $post, string $prefix = '', int $depth = 0): array
    {
        $out = [];
        foreach ($post as $k => $v) {
            $name = $prefix === '' ? (string) $k : $prefix . '[' . $k . ']';
            if (is_string($v)) {
                $out[$name] = $v;
            } elseif (is_array($v) && $depth < self::MAX_DEPTH) {
                $out += self::flatten($v, $name, $depth + 1);
            }
        }

        return $out;
    }

    private static function secret(string $name): bool
    {
        return $name === '_csrf' || $name === '_hash'
            || preg_match('/pass|token|secret|_csrf|_hash|confirm_empty/i', $name) === 1
            || preg_match('/(^|\[)(current|new|repeat|password|pass)(\]|\[|$)/i', $name) === 1;
    }

    /** Поле с оформлением: в браузере его значение — HTML редактора, человеку его показываем текстом. */
    private static function isRichtext(string $path, string $name): bool
    {
        if (preg_match('/^text\[(.+)\]$/', $name, $m) === 1) {
            return ($m[1] !== '' && (Readiness::registry()[$m[1]]['type'] ?? '') === 'richtext');
        }
        if (preg_match('#^/admin/([a-z0-9-]+)/(?:new|[a-f0-9]{12})/$#', $path, $m) === 1 && isset(Collections::all()[$m[1]])) {
            foreach (Collections::get($m[1])['fields'] as $f) {
                if ($f['name'] === $name) {
                    return $f['type'] === 'richtext';
                }
            }
        }

        return false;
    }

    /** HTML → текст: концы блоков и <br> становятся переводами строки, теги снимаются. */
    private static function plain(string $html): string
    {
        $text = preg_replace('#<br\s*/?>|</(p|div|li|h[1-6]|blockquote)>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    private static function label(string $path, string $name): string
    {
        if (preg_match('/^text\[(.+)\]$/', $name, $m) === 1) {
            return (string) (Readiness::registry()[$m[1]]['label'] ?? $m[1]);
        }
        if (preg_match('/^settings\[(.+)\]$/', $name, $m) === 1) {
            return (string) (SettingsController::fields()[$m[1]]['label'] ?? $m[1]);
        }
        if (preg_match('#^/admin/([a-z0-9-]+)/(?:new|[a-f0-9]{12})/$#', $path, $m) === 1 && isset(Collections::all()[$m[1]])) {
            foreach (Collections::get($m[1])['fields'] as $f) {
                if ($f['name'] === $name) {
                    return (string) $f['label'];
                }
            }
        }

        return $name;
    }

    /**
     * Страница, куда вернуться после входа — та, с которой отправили форму: форма правки (адрес POST совпадает с её адресом),
     * подтверждение удаления, список (быстрые кнопки статуса, порядка, видимости), история (откат), смена пароля.
     */
    public static function next(string $path): ?string
    {
        if (preg_match('#^/admin/(texts/[a-z]+|settings|password|[a-z0-9-]+/(?:new|[a-f0-9]{12})|[a-z0-9-]+/[a-f0-9]{12}/delete)/$#', $path) === 1) {
            return $path;
        }
        if (preg_match('#^/admin/([a-z0-9-]+)/[a-f0-9]{12}/(?:move|visibility|status)/$#', $path, $m) === 1) {
            return '/admin/' . $m[1] . '/';
        }

        return $path === '/admin/history/restore/' ? '/admin/history/' : null;
    }
}
