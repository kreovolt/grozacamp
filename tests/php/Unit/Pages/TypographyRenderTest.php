<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Pages;

use Groza\App;
use Groza\Support\Session;
use Groza\Tests\Support\AdminHttp;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/**
 * Типограф на публичных страницах: неразрывные пробелы в подвале, на «Контактах» и в текстах с оформлением.
 * В админку и в хранимые данные типограф не попадает никогда.
 */
final class TypographyRenderTest extends TestCase
{
    private const N = "\u{00A0}";

    private App $app;

    protected function setUp(): void
    {
        $this->app = TestApp::make();
    }

    protected function tearDown(): void
    {
        Session::reset();
    }

    public function test_footer_address_does_not_break_after_abbreviations_or_before_the_house_number(): void
    {
        $n = self::N;
        $body = TestApp::get($this->app, '/')->body();
        $this->assertSame(1, preg_match('#<footer.*</footer>#us', $body, $m));
        $this->assertStringContainsString("Офис: г.{$n}Пермь, ул.{$n}Милиционера Власова,{$n}37", $m[0]);
        $this->assertStringContainsString("<span class=\"nobr\">Пн–Пт</span>{$n}<span class=\"nobr\">10:00–19:00</span>", $m[0], 'часы офиса');
        $this->assertStringContainsString("+7{$n}(342){$n}247-43-47", $m[0], 'телефон');
    }

    public function test_contacts_page_address_hours_and_phone(): void
    {
        $n = self::N;
        $body = TestApp::get($this->app, '/contacts/')->body();
        $this->assertStringContainsString("<span>г.{$n}Пермь, ул.{$n}Милиционера Власова,{$n}37</span>", $body);
        $this->assertStringContainsString("<span class=\"nobr\">Сб–Вс</span>{$n}<span class=\"nobr\">12:00–17:00</span>", $body);
        // Телефон — одним куском: и неразрывные пробелы, и nowrap у ссылки
        $this->assertMatchesRegularExpression('#<a class="[^"]*whitespace-nowrap[^"]*" href="tel:\+73422474347"><span class="nobr">\+7' . $n . '\(342\)' . $n . '247-43-47</span></a>#u', $body);
        $this->assertStringContainsString("Быстрее всего ответим по{$n}телефону или в{$n}WhatsApp", $body, 'текст шаблона тоже через типограф');
    }

    public function test_every_phone_link_on_public_pages_does_not_wrap(): void
    {
        foreach (['/', '/contacts/', '/booking/', '/info/', '/vacancies/', '/team/', '/svedeniya/ob-organizacii/', '/nesushchestvuyushchaya/'] as $path) {
            $body = TestApp::get($this->app, $path)->body();
            preg_match_all('#<a\b[^>]*href="tel:[^"]*"[^>]*>(.*?)</a>#us', $body, $links, PREG_SET_ORDER);
            foreach ($links as [$tag, $text]) {
                if (preg_match('/\d/', strip_tags($text)) !== 1) {
                    continue; // кнопка «Позвонить» или иконка — номера в тексте нет
                }
                $this->assertMatchesRegularExpression('#class="[^"]*(whitespace-nowrap|nobr)#', $tag . $text, "$path: $tag");
            }
        }
    }

    public function test_richtext_blocks_get_no_break_spaces_in_text_nodes(): void
    {
        $n = self::N;
        $texts = $this->app->store()->read('texts');
        $texts['info.prepare'] = '<p>Лагерь в лесу, <a href="https://vk.com/grozacamp">vk.com/grozacamp</a> и <strong>до 24 детей</strong></p>';
        $this->app->store()->write('texts', $texts, 'test');

        $body = TestApp::get($this->app, '/info/')->body();
        $this->assertStringContainsString("<p>Лагерь в{$n}лесу, <a href=\"https://vk.com/grozacamp\" rel=\"noopener noreferrer\">vk.com/grozacamp</a> и{$n}<strong>до{$n}24{$n}детей</strong></p>", $body);
    }

    public function test_text_page_headings_keep_their_anchors(): void
    {
        $n = self::N;
        $texts = $this->app->store()->read('texts');
        $texts['policy'] = '<h3>Права и обязанности</h3><p>Оператор в праве</p><h3>Срок хранения</h3><p>Год</p>';
        $this->app->store()->write('texts', $texts, 'test');

        $body = TestApp::get($this->app, '/policy/')->body();
        $this->assertStringContainsString("<h3 id=\"prava-i-obyazannosti\">Права и{$n}обязанности</h3>", $body, 'id — от исходного текста, не от типографа');
        $this->assertStringContainsString("href=\"#prava-i-obyazannosti\">Права и{$n}обязанности</a>", $body, 'оглавление');
        $this->assertStringContainsString("Оператор в{$n}праве", $body);
    }

    public function test_svedeniya_order_wording_and_values(): void
    {
        $n = self::N;
        $body = TestApp::get($this->app, '/svedeniya/ob-organizacii/')->body();
        $this->assertStringContainsString("Лучников Евгений Алексеевич", $body);
        $this->assertStringContainsString("г.{$n}Пермь, ул.{$n}Милиционера Власова,{$n}37", $body, 'адреса');
        $this->assertStringContainsString("<span class=\"nobr\">Пн–Пт</span>{$n}<span class=\"nobr\">10:00–19:00</span>", $body, 'режим работы (richtext) и часы');
    }

    public function test_admin_settings_form_shows_the_stored_address_exactly(): void
    {
        $before = $this->app->store()->read('settings');
        $hash = $this->app->store()->hash('settings');
        foreach (['/', '/contacts/', '/svedeniya/ob-organizacii/'] as $path) {
            TestApp::get($this->app, $path);
        }

        AdminHttp::user($this->app);
        AdminHttp::login($this->app);
        $admin = AdminHttp::get($this->app, '/admin/settings/')->body();
        $this->assertStringContainsString('value="' . htmlspecialchars((string) $before['officeAddress'], ENT_QUOTES) . '"', $admin);
        $this->assertStringContainsString('value="г. Пермь, ул. Милиционера Власова, 37"', $admin);
        $this->assertStringNotContainsString(self::N, $admin, 'в админке нет неразрывных пробелов типографа');
        $this->assertStringNotContainsString('class="nobr"', $admin);

        $this->assertSame($hash, $this->app->store()->hash('settings'), 'показ страниц не меняет хранимые данные');
        $this->assertSame($before, $this->app->store()->read('settings'));
    }

    public function test_admin_text_editor_shows_richtext_without_typography(): void
    {
        $texts = $this->app->store()->read('texts');
        $texts['info.prepare'] = '<p>Лагерь в лесу</p>';
        $this->app->store()->write('texts', $texts, 'test');
        TestApp::get($this->app, '/info/');

        AdminHttp::user($this->app);
        AdminHttp::login($this->app);
        $admin = AdminHttp::get($this->app, '/admin/texts/info/')->body();
        $this->assertStringContainsString('&lt;p&gt;Лагерь в лесу&lt;/p&gt;', $admin);
        $this->assertSame('<p>Лагерь в лесу</p>', $this->app->store()->read('texts')['info.prepare']);
    }
}
