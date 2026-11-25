<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Pages;

use Groza\App;
use Groza\Domain\Shifts;
use Groza\Tests\Support\TestApp;
use Groza\Tests\Unit\Data\CopyLintTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Groza\View\photo;

/** Главная (спека §7.1): 12 блоков из данных seed/demo, часы приложения — 2027-01-15. */
final class HomePageTest extends TestCase
{
    private const BLOCKS = ['hero', 'stats', 'shifts', 'kids', 'care', 'steps', 'included', 'base', 'team', 'reviews', 'faq', 'cta'];
    private const MILITARY_ROLE = '/военн|НВП|огнев|тактик/iu';
    /** Фото из ВК, выведенные после перехода на папку заказчика (photo-selection.md). */
    private const RETIRED_PHOTOS = [
        'hero-river', 'kids-evening', 'kids-girls', 'kids-boy', 'kids-bubbles', 'kids-teens', 'kids-gazebo', 'kids-tent',
        'counselors', 'counselors-kids', 'squad-river', 'toss', 'spas-mchs', 'spas-demo', 'spas-fire', 'spas-laugh',
        'sturm-smile', 'sturm-relay', 'sturm-theatre', 'sturm-cheer',
    ];

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

    /** @return array<string,array{string}> */
    public static function seedSets(): array
    {
        return ['seed/data' => ['data'], 'seed/demo' => ['demo']];
    }

    /** Анти-цель «мама пугается»: на главной — руководство, педагоги-организаторы, вожатые; без «военных» должностей. */
    #[DataProvider('seedSets')]
    public function test_on_home_team_roles_are_not_military(string $set): void
    {
        $file = dirname(__DIR__, 4) . "/seed/{$set}/team.json";
        $team = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR)['items'];
        $roles = array_column(array_filter($team, static fn (array $m): bool => !empty($m['onHome']) && empty($m['hidden'])), 'role');

        $this->assertGreaterThanOrEqual(4, count($roles), 'на главной 4–6 человек');
        $this->assertSame([], array_values(preg_grep(self::MILITARY_ROLE, $roles)), "seed/{$set}");
    }

    public function test_team_strip_has_no_military_roles(): void
    {
        $team = $this->block(TestApp::get(TestApp::make(), '/')->body(), 'team');

        $this->assertDoesNotMatchRegularExpression(self::MILITARY_ROLE, strip_tags($team));
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

        $this->assertMatchesRegularExpression('#<img[^>]*src="/assets/img/friends-selfie-[0-9]+\.jpg"[^>]*loading="eager" fetchpriority="high"#', $hero);
    }

    /** Герой — селфи вожатого с ребятами из папки заказчика (решение пользователя, photo-selection.md). */
    public function test_hero_photo_is_friends_selfie(): void
    {
        $hero = $this->block(TestApp::get(TestApp::make(), '/')->body(), 'hero');

        $this->assertSame(1, preg_match_all('#<img\b[^>]*>#', $hero, $imgs), 'в герое одно фото');
        $this->assertMatchesRegularExpression('#\ssrc="/assets/img/friends-selfie-[0-9]+\.jpg"#', $imgs[0][0]);
        $this->assertMatchesRegularExpression('#\ssrcset="/assets/img/friends-selfie-480\.jpg 480w,.*friends-selfie-1920\.jpg 1920w"#', $imgs[0][0]);
        preg_match_all('#<source\b[^>]*srcset="([^"]*)"#', $hero, $sources);
        $this->assertCount(2, $sources[1], 'avif и webp');
        foreach ($sources[1] as $srcset) {
            $this->assertMatchesRegularExpression('#^/assets/img/friends-selfie-480\.(avif|webp) 480w#', $srcset);
        }
    }

    public function test_home_has_no_retired_photo_keys(): void
    {
        $html = TestApp::get(TestApp::make(), '/')->body();

        foreach (self::RETIRED_PHOTOS as $key) {
            $this->assertDoesNotMatchRegularExpression('#/assets/img/' . preg_quote($key, '#') . '-[0-9]+\.#', $html, $key);
        }
    }

    /** Блоки главной показывают фото заказчика по таблице photo-selection.md. */
    public function test_home_blocks_use_client_photos(): void
    {
        $html = TestApp::get(TestApp::make(), '/')->body();
        $expected = [
            'kids' => ['team-poster', 'stage-cheer', 'games-meadow'],
            'care' => ['ropes-instructor', 'first-aid-field'],
            'steps' => ['evening-circle', 'games-chairs', 'awards-group'],
            'cta' => ['campfire'],
        ];
        foreach ($expected as $block => $keys) {
            $part = $this->block($html, $block);
            foreach ($keys as $key) {
                $this->assertStringContainsString('/assets/img/' . $key . '-', $part, "{$block}: {$key}");
            }
        }
        $shifts = $this->block($html, 'shifts');
        $this->assertStringContainsString('/assets/img/spas-mchs-suit-', $this->card($shifts, 'spasatel-2027'));
        $this->assertStringContainsString('/assets/img/ropes-zipline-', $this->card($shifts, 'shturmovik-2027'));
        $this->assertStringContainsString('/assets/img/ropes-helmet-', $this->card($shifts, 'pogranichnik-2027'));
    }

    /** У каждого фото на главной — описание того, что на снимке, а не «фото» / «фото 2 из 5». */
    public function test_every_photo_has_descriptive_alt(): void
    {
        $html = TestApp::get(TestApp::make(), '/')->body();
        preg_match_all('#<img\b[^>]*\ssrc="/assets/img/[^"]*"[^>]*>#', $html, $imgs);

        $this->assertGreaterThanOrEqual(15, count($imgs[0]), 'герой, смены, коллажи, базы, призыв');
        foreach ($imgs[0] as $img) {
            $this->assertMatchesRegularExpression('#\salt="([^"]*)"#', $img);
            preg_match('#\salt="([^"]*)"#', $img, $m);
            $alt = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $this->assertNotSame('', $alt, $img);
            $this->assertDoesNotMatchRegularExpression('#^фото$|(?:^|\s)фото\s+\d#iu', $alt, $img);
            $this->assertGreaterThanOrEqual(3, count(preg_split('#\s+#u', $alt)), "в описании хотя бы три слова: {$alt}");
        }
    }

    /** Ключи фото в примерных и подтверждённых данных есть в манифесте (выведенные ключи не остались в данных). */
    #[DataProvider('seedSets')]
    public function test_seed_photo_keys_exist_in_manifest(string $set): void
    {
        $dir = dirname(__DIR__, 4) . "/seed/{$set}";
        $keys = [];
        foreach (['shifts' => 'photo', 'venues' => 'photos'] as $file => $field) {
            $items = json_decode((string) file_get_contents("{$dir}/{$file}.json"), true, 512, JSON_THROW_ON_ERROR)['items'];
            foreach ($items as $item) {
                foreach ((array) ($item[$field] ?? []) as $value) {
                    if (is_string($value)) {
                        $keys[] = $value;
                    }
                }
            }
        }
        foreach (array_unique($keys) as $key) {
            $this->assertNotContains($key, self::RETIRED_PHOTOS, "seed/{$set}");
            $this->assertSame('/assets/img/' . $key, photo($key)['src'], "seed/{$set}: {$key}");
        }
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
