<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\View;

use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/** Макет сайта целиком: шапка, меню, подвал, контакты из настроек. */
final class LayoutTest extends TestCase
{
    public function test_page_uses_layout_with_russian_lang_and_title(): void
    {
        $res = TestApp::get(TestApp::make(), '/vacancies/');
        $html = $res->body();

        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('<html lang="ru"', $html);
        $this->assertMatchesRegularExpression('#<title>Вакансии — [^<]*«Гроза»[^<]*</title>#u', $html);
        $this->assertMatchesRegularExpression('#<h1[^>]*>\s*Вакансии\s*</h1>#u', $html);
        $this->assertStringContainsString('id="main"', $html);
    }

    public function test_header_menu_in_spec_order_with_current_page_marked(): void
    {
        $html = TestApp::get(TestApp::make(), '/vacancies/')->body();
        $nav = $this->between($html, 'aria-label="Основное меню"', '</nav>');

        $positions = array_map(
            static fn (string $label): int|false => mb_strpos($nav, $label),
            ['Купить путёвку', 'Информация для родителей', 'Вакансии', 'Сведения об организации отдыха детей и их оздоровления']
        );
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'порядок пунктов как в спеке §5.2');
        $this->assertMatchesRegularExpression('#<a[^>]*href="/vacancies/"[^>]*aria-current="page"#u', $nav);
        $this->assertMatchesRegularExpression('#<button[^>]*aria-expanded="false"[^>]*aria-controls="#u', $nav);
    }

    public function test_header_and_footer_show_default_contacts(): void
    {
        $html = TestApp::get(TestApp::make(), '/')->body();

        $this->assertStringContainsString('href="tel:+73422474347"', $html);
        $this->assertStringContainsString('+7 (342) 247-43-47', $html);
        $this->assertStringContainsString('https://wa.me/79991239300', $html);
        $this->assertStringContainsString('mailto:2474347@bk.ru', $html);
        $this->assertStringContainsString('ул. Милиционера Власова, 37', $html);
        $this->assertStringContainsString('Пн–Пт 10:00–19:00', $html);
        $this->assertStringContainsString('https://vk.com/grozacamp', $html);
        $this->assertStringContainsString('https://www.youtube.com/channel/UCfKD7JRX5wEmFvLhp5KWawQ', $html);
    }

    public function test_settings_from_store_override_defaults(): void
    {
        $app = TestApp::make();
        $app->store()->write('settings', ['phone' => '+79990001122', 'youtube' => ''], 'test');
        $html = TestApp::get($app, '/')->body();

        $this->assertStringContainsString('+7 (999) 000-11-22', $html);
        $this->assertStringContainsString('href="tel:+79990001122"', $html);
        $this->assertStringNotContainsString('+7 (342) 247-43-47', $html);
        $this->assertStringNotContainsString('youtube.com', $html, 'пустая ссылка не выводится');
        $this->assertStringContainsString('2474347@bk.ru', $html, 'остальное берётся из умолчаний');
    }

    public function test_footer_columns(): void
    {
        $html = TestApp::get(TestApp::make(), '/')->body();
        $footer = $this->between($html, '<footer', '</footer>');

        foreach (['Лагерю', 'Сведения', 'Контакты', 'Смены', 'Команда', 'Педагогический и вожатский состав', 'Доступная среда', 'Политика обработки персональных данных', 'Правила пребывания'] as $text) {
            $this->assertStringContainsString($text, $footer, $text);
        }
        $this->assertSame(11, preg_match_all('#href="/svedeniya/[a-z-]+/(\#[a-z-]+)?"#', $footer), '6 разделов + 5 подразделов');
        $this->assertMatchesRegularExpression('#© [^<]*«Гроза»[^<]*2027#u', $footer, 'год из часов приложения');
    }

    public function test_assets_are_versioned_and_local(): void
    {
        $html = TestApp::get(TestApp::make(), '/')->body();

        $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="/assets/css/app\.css\?v=[a-f0-9]{10}">#', $html);
        $this->assertMatchesRegularExpression('#<script src="/assets/js/main\.js\?v=[a-f0-9]{10}" defer></script>#', $html);
        $this->assertSame(2, substr_count($html, 'rel="preload"'), 'предзагрузка только двух шрифтов');
        $this->assertDoesNotMatchRegularExpression('#(src|href)="(https?:)?//(?!vk\.com|wa\.me|www\.youtube\.com)#', $html, 'никаких внешних ресурсов, кроме ссылок на соцсети');
    }

    public function test_breadcrumbs_on_inner_svedeniya_page(): void
    {
        $html = TestApp::get(TestApp::make(), '/svedeniya/mto/')->body();
        $crumbs = $this->between($html, 'aria-label="Хлебные крошки"', '</nav>');

        $this->assertStringContainsString('href="/"', $crumbs);
        $this->assertStringContainsString('href="/svedeniya/"', $crumbs);
        $this->assertMatchesRegularExpression('#aria-current="page"[^>]*>\s*Материально-техническое обеспечение и оснащённость#u', $crumbs);
    }

    public function test_every_main_nav_href_resolves(): void
    {
        $app = TestApp::make();
        $hrefs = ['/booking/', '/info/', '/vacancies/', '/svedeniya/', '/svedeniya/ob-organizacii/', '/svedeniya/deyatelnost/',
                  '/svedeniya/mto/', '/svedeniya/uslugi/', '/svedeniya/dostupnaya-sreda/', '/svedeniya/inaya-informaciya/'];
        foreach ($hrefs as $href) {
            $this->assertSame(200, TestApp::get($app, $href)->status(), $href);
        }
    }

    private function between(string $html, string $start, string $end): string
    {
        $from = strpos($html, $start);
        $this->assertNotFalse($from, "нет «{$start}»");
        $to = strpos($html, $end, (int) $from);
        $this->assertNotFalse($to, "нет «{$end}» после «{$start}»");

        return substr($html, (int) $from, (int) $to - (int) $from);
    }
}
