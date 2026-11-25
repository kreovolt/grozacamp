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
        if (!in_array($path, self::SKIP_PATHS, true)) {
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
                $items[] = ['label' => self::label($path, $name), 'value' => $value];
            }
        }

        return ['items' => $items, 'truncated' => $truncated, 'next' => self::next($path)];
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
        return $name === '_csrf' || $name === '_hash' || preg_match('/pass|token|secret|_csrf|_hash|confirm_empty/i', $name) === 1;
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

    /** Страница, куда вернуться после входа: форма правки (адрес POST совпадает с адресом самой формы). */
    private static function next(string $path): ?string
    {
        return preg_match('#^/admin/(texts/[a-z]+|settings|[a-z0-9-]+/(?:new|[a-f0-9]{12}))/$#', $path) === 1 ? $path : null;
    }
}
