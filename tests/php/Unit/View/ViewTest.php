<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\View;

use Groza\View\View;
use PHPUnit\Framework\TestCase;

use function Groza\View\asset;
use function Groza\View\e;
use function Groza\View\icon;
use function Groza\View\pending;
use function Groza\View\tel;

final class ViewTest extends TestCase
{
    private function view(): View
    {
        return new View(dirname(__DIR__, 3) . '/fixtures/views', ['site' => 'groza', 'name' => 'из глобальных']);
    }

    public function test_e_escapes_html(): void
    {
        $this->assertSame('&lt;script&gt;', e('<script>'));
        $this->assertSame('&quot;a&quot; &#039;b&#039;', e('"a" \'b\''));
        $this->assertSame('', e(null));
    }

    public function test_pending_shows_placeholder_for_empty(): void
    {
        $this->assertStringContainsString('Информация обновляется', pending(''));
        $this->assertStringContainsString('class="pending"', pending(null));
        $this->assertStringContainsString('Информация обновляется', pending("  \n"));
    }

    public function test_pending_escapes_value(): void
    {
        $this->assertSame('a&amp;b', pending('a&b'));
    }

    public function test_layout_wraps_content_and_sets_title(): void
    {
        $html = $this->view()->render('page', ['name' => 'Вася']);

        $this->assertStringContainsString('<title>Привет &lt;мир&gt;</title>', $html);
        $this->assertMatchesRegularExpression('#<main>\s*<p>Вася</p>\s*</main>#u', $html);
        $this->assertStringContainsString('data-site="groza"', $html, 'глобальные переменные видны в макете');
    }

    public function test_layouts_nest(): void
    {
        $html = $this->view()->render('nested');

        $this->assertMatchesRegularExpression('#<main>\s*<section data-label="внутренний">\s*<b>ядро</b>\s*</section>\s*</main>#u', $html);
        $this->assertStringContainsString('<title>Оболочка</title>', $html);
    }

    public function test_local_vars_override_globals(): void
    {
        $this->assertStringContainsString('<p>из глобальных</p>', $this->view()->render('page'));
        $this->assertStringContainsString('<p>Петя</p>', $this->view()->render('page', ['name' => 'Петя']));
    }

    public function test_partial_gets_own_vars_and_globals_without_leaking(): void
    {
        $html = $this->view()->render('with-partial');

        $this->assertMatchesRegularExpression('#<span>Аня@groza</span>\s*\|чисто#u', $html);
    }

    public function test_unknown_or_unsafe_template_throws(): void
    {
        foreach (['missing', '../views/page', 'partials/../page', '/etc/passwd', 'Page'] as $name) {
            try {
                $this->view()->render($name);
                $this->fail("ожидалось исключение для $name");
            } catch (\InvalidArgumentException|\RuntimeException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function test_exception_in_template_discards_buffers(): void
    {
        $level = ob_get_level();
        try {
            $this->view()->render('broken');
            $this->fail('ожидалось исключение');
        } catch (\RuntimeException $e) {
            $this->assertSame('сломано', $e->getMessage());
        }
        $this->assertSame($level, ob_get_level());
    }

    public function test_share_sets_global(): void
    {
        $view = $this->view();
        $view->share('site', 'другой');

        $this->assertStringContainsString('data-site="другой"', $view->render('page'));
    }

    public function test_tel_builds_dialable_href(): void
    {
        $this->assertSame('tel:+73422474347', tel('+73422474347'));
        $this->assertSame('tel:+73422474347', tel('+7 (342) 247-43-47'));
    }

    public function test_asset_appends_hash_from_manifest(): void
    {
        $this->assertMatchesRegularExpression('#^/assets/css/app\.css\?v=[a-f0-9]{10}$#', asset('/assets/css/app.css'));
        $this->assertMatchesRegularExpression('#^/assets/js/main\.js\?v=[a-f0-9]{10}$#', asset('/assets/js/main.js'));
        $this->assertSame('/assets/nope.css', asset('/assets/nope.css'));
    }

    public function test_icon_uses_versioned_sprite(): void
    {
        $svg = icon('phone', 'size-4');

        $this->assertMatchesRegularExpression('#<use href="/assets/icons\.svg\?v=[a-f0-9]{10}\#i-phone"></use>#', $svg);
        $this->assertStringContainsString('class="icon size-4"', $svg);
        $this->assertStringContainsString('aria-hidden="true"', $svg);
        $this->assertStringContainsString('focusable="false"', $svg);
    }

    public function test_every_icon_used_in_templates_exists_in_sprite(): void
    {
        $root = dirname(__DIR__, 4);
        $sprite = (string) file_get_contents($root . '/public/assets/icons.svg');
        $used = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/templates', \FilesystemIterator::SKIP_DOTS)) as $file) {
            preg_match_all("/icon\\('([a-z0-9-]+)'/", (string) file_get_contents($file->getPathname()), $m);
            $used = [...$used, ...$m[1]];
        }

        $this->assertNotEmpty($used);
        foreach (array_unique($used) as $name) {
            $this->assertStringContainsString('id="i-' . $name . '"', $sprite, $name);
        }
    }
}
