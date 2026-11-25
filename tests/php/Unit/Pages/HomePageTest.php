<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Pages;

use Groza\App;
use Groza\Domain\Shifts;
use Groza\Tests\Support\TestApp;
use Groza\Tests\Unit\Data\CopyLintTest;
use PHPUnit\Framework\TestCase;

/** Главная (спека §7.1): 12 блоков из данных seed/demo, часы приложения — 2027-01-15. */
final class HomePageTest extends TestCase
{
    private const BLOCKS = ['hero', 'stats', 'shifts', 'kids', 'care', 'steps', 'included', 'base', 'team', 'reviews', 'faq', 'cta'];

    public function test_home_renders_all_blocks_in_spec_order(): void
    {
        $res = TestApp::get(TestApp::make(), '/');

        $this->assertSame(200, $res->status());
        preg_match_all('#data-block="([a-z]+)"#', $res->body(), $m);
        $this->assertSame(self::BLOCKS, $m[1]);
        $this->assertMatchesRegularExpression('#<h1[^>]*>\s*Лагерь настоящих приключений\s*</h1>#u', $res->body());
    }

    public function test_kicker_override_wins(): void
    {
        $app = TestApp::make();
        $this->setTexts($app, ['home.heroKicker' => 'Своё']);
        $hero = $this->block(TestApp::get($app, '/')->body(), 'hero');

        $this->assertStringContainsString('Своё', $hero);
        $this->assertStringNotContainsString('Смены:', $hero);
    }

    public function test_kicker_auto_from_nearest_season(): void
    {
        $app = TestApp::make();
        $this->setTexts($app, ['home.heroKicker' => '  ']);
        $hero = $this->block(TestApp::get($app, '/')->body(), 'hero');

        $this->assertStringContainsString('Смены: Лето 2027 (пример) · Пермский край', $hero);
    }

    public function test_kicker_without_shifts(): void
    {
        $app = TestApp::make();
        $app->store()->write('shifts', ['items' => []], 'test');
        $html = TestApp::get($app, '/')->body();

        $this->assertStringContainsString('Детский лагерь · Пермский край', $this->block($html, 'hero'));
        $this->assertSame(0, $this->countClass($html, 'shift-card'));
        $this->assertStringContainsString('href="/booking/"', $this->block($html, 'shifts'), 'без смен — путь к заявке');
    }

    public function test_shift_cards_match_visible(): void
    {
        $app = TestApp::make();
        $items = $app->store()->read('shifts')['items'];
        $visible = Shifts::visible($items, $app->clock()->now());
        $shifts = $this->block(TestApp::get($app, '/')->body(), 'shifts');

        $this->assertCount(3, $visible, 'демо: весенняя смена скрыта');
        $this->assertSame(count($visible), $this->countClass($shifts, 'shift-card'));
        foreach ($visible as $shift) {
            $this->assertStringContainsString('href="/program/' . $shift['slug'] . '/"', $shifts);
        }
        $this->assertStringNotContainsString('Вперёд и вверх', $shifts, 'скрытая смена не показывается');

        $items[0]['hidden'] = true;
        $app->store()->write('shifts', ['items' => $items], 'test');
        $this->assertSame(2, $this->countClass($this->block(TestApp::get($app, '/')->body(), 'shifts'), 'shift-card'));
    }

    public function test_shift_card_shows_dates_length_age_price_and_status(): void
    {
        $shifts = $this->block(TestApp::get(TestApp::make(), '/')->body(), 'shifts');
        $card = $this->card($shifts, 'spasatel-2027');

        $this->assertStringContainsString('Спасатель', $card);
        $this->assertStringContainsString('2–22 июля 2027', $card);
        $this->assertStringContainsString('21 день', $card);
        $this->assertStringContainsString('8–17 лет', $card);
        $this->assertStringContainsString("60\u{00A0}000\u{00A0}₽", $card, 'разряды и знак рубля — неразрывными пробелами');
        $this->assertStringContainsString('Идёт набор', $card);
        $this->assertStringContainsString('ДЗОЛ «Солнышко»', $card);
        $this->assertStringContainsString('href="/booking/?smena=spasatel-2027#zayavka"', $card);

        $this->assertStringContainsString('14 дней', $this->card($shifts, 'pogranichnik-2027'));
        $this->assertStringContainsString('Ранняя цена до 1 марта (пример)', $this->card($shifts, 'shturmovik-2027'));
        $this->assertStringContainsString('Мало мест', $this->card($shifts, 'shturmovik-2027'));
    }

    public function test_closed_shift_has_badge_and_no_booking_button(): void
    {
        $card = $this->card($this->block(TestApp::get(TestApp::make(), '/')->body(), 'shifts'), 'pogranichnik-2027');

        $this->assertStringContainsString('Мест нет', $card);
        $this->assertStringNotContainsString('Забронировать', $card);
        $this->assertStringNotContainsString('/booking/?smena=pogranichnik-2027', $card);
        $this->assertStringContainsString('href="/program/pogranichnik-2027/"', $card, '«Подробнее» остаётся');
    }

    public function test_shift_without_price_asks_to_check_with_office(): void
    {
        $app = TestApp::make();
        $items = $app->store()->read('shifts')['items'];
        $items[0]['price'] = null;
        $app->store()->write('shifts', ['items' => $items], 'test');
        $card = $this->card($this->block(TestApp::get($app, '/')->body(), 'shifts'), 'spasatel-2027');

        $this->assertStringContainsString('Стоимость уточняйте в офисе', $card);
        $this->assertStringNotContainsString('₽', $card);
    }

    public function test_stats_band_from_texts(): void
    {
        $stats = $this->block(TestApp::get(TestApp::make(), '/')->body(), 'stats');

        $this->assertSame(4, substr_count($stats, '<li'));
        $this->assertStringContainsString('до 24', $stats);
        $this->assertStringNotContainsString('24/7', $stats);
    }

    public function test_team_strip_max_6_on_home(): void
    {
        $app = TestApp::make();
        $items = $app->store()->read('team')['items'];
        foreach ($items as $i => $member) {
            $items[$i]['onHome'] = true;
        }
        $items[0]['hidden'] = true; // директор скрыт с сайта — не показывается и на главной
        $app->store()->write('team', ['items' => $items], 'test');
        $team = $this->block(TestApp::get($app, '/')->body(), 'team');

        $this->assertSame(6, $this->countClass($team, 'team-member'));
        $this->assertStringNotContainsString('Лучников', $team);
        $this->assertStringContainsString('Корнейчук', $team, 'порядок — по полю order');
        $this->assertStringNotContainsString('Тараканов', $team, 'седьмой и дальше не помещаются');
        $this->assertStringContainsString('href="/team/"', $team);
    }

    public function test_team_strip_shows_only_marked_for_home(): void
    {
        $team = $this->block(TestApp::get(TestApp::make(), '/')->body(), 'team');

        $this->assertStringContainsString('Лучников', $team);
        $this->assertStringNotContainsString('Манохин', $team, 'не отмечен «на главную»');
    }

    public function test_faq_max_5_on_home(): void
    {
        $app = TestApp::make();
        $items = $app->store()->read('faq')['items'];
        foreach ($items as $i => $item) {
            $items[$i]['onHome'] = true;
        }
        $app->store()->write('faq', ['items' => $items], 'test');
        $faq = $this->block(TestApp::get($app, '/')->body(), 'faq');

        $this->assertSame(5, substr_count($faq, '<details'));
        $this->assertStringContainsString('Где живут ребята?', $faq, 'первые по порядку');
        $this->assertStringNotContainsString('Как ребёнок доберётся до лагеря?', $faq, 'шестой не показывается');
        $this->assertStringContainsString('href="/info/#faq"', $faq);
    }

    public function test_faq_shows_only_marked_for_home(): void
    {
        $faq = $this->block(TestApp::get(TestApp::make(), '/')->body(), 'faq');

        $this->assertStringContainsString('Это безопасно?', $faq);
        $this->assertStringNotContainsString('Что с клещами?', $faq);
    }

    public function test_reviews_show_visible_quotes(): void
    {
        $app = TestApp::make();
        $items = $app->store()->read('reviews')['items'];
        $items[1]['visible'] = false;
        $app->store()->write('reviews', ['items' => $items], 'test');
        $reviews = $this->block(TestApp::get($app, '/')->body(), 'reviews');

        $this->assertSame(2, $this->countClass($reviews, 'review'));
        $this->assertStringNotContainsString('Дочь ездит второй раз', $reviews);
    }

    public function test_reviews_fallback_to_vk_link(): void
    {
        $app = TestApp::make();
        $app->store()->write('reviews', ['items' => []], 'test');
        $reviews = $this->block(TestApp::get($app, '/')->body(), 'reviews');

        $this->assertSame(0, $this->countClass($reviews, 'review'));
        $this->assertStringContainsString('href="https://vk.com/topic-185063757_43871120"', $reviews);
    }

    public function test_base_block_shows_venues_with_visible_shifts(): void
    {
        $app = TestApp::make();
        $base = $this->block(TestApp::get($app, '/')->body(), 'base');

        $this->assertStringContainsString('ДЗОЛ «Солнышко»', $base);
        $this->assertStringNotContainsString('ДЗОЛ «Восток»', $base, 'на «Востоке» только скрытая смена');
        $this->assertStringContainsString('/assets/img/base-cabins-', $base);
    }

    public function test_included_list_and_schedule_from_texts(): void
    {
        $html = TestApp::get(TestApp::make(), '/')->body();
        $included = $this->block($html, 'included');
        $steps = $this->block($html, 'steps');

        $this->assertStringContainsString('Трансфер из Перми в лагерь и обратно', $included);
        $this->assertStringContainsString('Принимаем сертификаты', $included);
        $this->assertMatchesRegularExpression('#<details[^>]*>\s*<summary[^>]*>.*Распорядок дня#su', $steps, 'распорядок раскрывается без JS');
        $this->assertStringContainsString('22:20–22:50', $steps);
        $this->assertStringContainsString('«Свечка», вечерний сбор отряда', $steps);
    }

    public function test_final_cta_has_booking_phone_and_whatsapp(): void
    {
        $cta = $this->block(TestApp::get(TestApp::make(), '/')->body(), 'cta');

        $this->assertStringContainsString('Готовы к приключению?', $cta);
        $this->assertStringContainsString('href="/booking/"', $cta);
        $this->assertStringContainsString('href="tel:+73422474347"', $cta);
        $this->assertStringContainsString('href="https://wa.me/79991239300"', $cta);
    }

    public function test_hero_photo_loads_eagerly_with_high_priority(): void
    {
        $hero = $this->block(TestApp::get(TestApp::make(), '/')->body(), 'hero');

        $this->assertMatchesRegularExpression('#<img[^>]*src="/assets/img/hero-river-[0-9]+\.jpg"[^>]*loading="eager" fetchpriority="high"#', $hero);
    }

    public function test_texts_are_escaped(): void
    {
        $app = TestApp::make();
        $this->setTexts($app, ['home.heroTitle' => 'Лагерь <script>alert(1)</script>']);
        $html = TestApp::get($app, '/')->body();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('Лагерь &lt;script&gt;', $html);
    }

    public function test_home_page_has_no_scary_words(): void
    {
        $app = TestApp::make();
        $html = TestApp::get($app, '/')->body();
        // названия смен — бренд (Global Constraints), их не проверяем
        foreach ($app->store()->read('shifts')['items'] as $shift) {
            $html = str_replace($shift['title'], '', $html);
        }
        $main = $this->between($html, '<main', '</main>');

        $this->assertSame([], CopyLintTest::scaryWords($main));
    }

    /** @param array<string,mixed> $values */
    private function setTexts(App $app, array $values): void
    {
        $app->store()->write('texts', array_replace($app->store()->read('texts'), $values), 'test');
    }

    /** Разметка блока главной: от data-block="$name" до следующего блока. */
    private function block(string $html, string $name): string
    {
        $from = strpos($html, 'data-block="' . $name . '"');
        $this->assertNotFalse($from, "нет блока {$name}");
        $to = strpos($html, 'data-block="', (int) $from + 1);

        return $to === false ? substr($html, (int) $from) : substr($html, (int) $from, $to - (int) $from);
    }

    /** Карточка смены по slug: <article class="shift-card…">…</article>. */
    private function card(string $html, string $slug): string
    {
        preg_match_all('#<article[^>]*class="[^"]*\bshift-card\b.*?</article>#su', $html, $m);
        foreach ($m[0] as $part) {
            if (str_contains($part, 'href="/program/' . $slug . '/"')) {
                return $part;
            }
        }
        $this->fail("нет карточки {$slug}");
    }

    private function countClass(string $html, string $class): int
    {
        return preg_match_all('#class="(?:[^"]*\s)?' . preg_quote($class, '#') . '(?:\s[^"]*)?"#', $html);
    }

    private function between(string $html, string $start, string $end): string
    {
        $from = strpos($html, $start);
        $this->assertNotFalse($from);
        $to = strpos($html, $end, (int) $from);
        $this->assertNotFalse($to);

        return substr($html, (int) $from, (int) $to - (int) $from);
    }
}
