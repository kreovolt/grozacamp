<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Pages;

use Groza\App;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/** «Родителям», вакансии, команда, контакты, правила, политика и 404 (спека §7.4, §7.5, §7.7). Данные — seed/demo, часы — 2027-01-15. */
final class ContentPagesTest extends TestCase
{
    private const INFO_IDS = ['prepare', 'packing', 'documents', 'contraindications', 'medicines', 'contact', 'transfer', 'visits', 'rules', 'faq'];

    /** @param array<string,mixed> $patch */
    private function withTexts(array $patch): App
    {
        $app = TestApp::make();
        $app->store()->write('texts', array_replace($app->store()->read('texts'), $patch), 'test');

        return $app;
    }

    /** Текст секции <section id="$id"> до её закрытия. */
    private function section(string $html, string $id): string
    {
        $this->assertSame(1, preg_match('#<section[^>]*\sid="' . $id . '"[^>]*>(.*?)</section>#us', $html, $m), 'нет секции #' . $id);

        return $m[1];
    }

    public function test_info_sections_in_order(): void
    {
        $res = TestApp::get(TestApp::make(), '/info/');
        $this->assertSame(200, $res->status());
        $this->assertMatchesRegularExpression('#<h1[^>]*>\s*Информация для родителей\s*</h1>#u', $res->body());
        preg_match_all('#<section[^>]*\sid="(' . implode('|', self::INFO_IDS) . ')"#u', $res->body(), $m);
        $this->assertSame(self::INFO_IDS, $m[1]);
        foreach (self::INFO_IDS as $id) {
            $this->assertStringContainsString('href="#' . $id . '"', $res->body(), 'оглавление: #' . $id);
        }
    }

    public function test_info_lists_are_shared(): void
    {
        $app = TestApp::make();
        $texts = $app->store()->read('texts');
        $body = TestApp::get($app, '/info/')->body();
        $packing = $this->section($body, 'packing');
        foreach ($texts['lists.packing'] as $item) {
            $this->assertStringContainsString(htmlspecialchars($item, ENT_QUOTES), $packing);
        }
        $this->assertSame(count($texts['lists.packing']), substr_count($packing, '<li'));
        $documents = $this->section($body, 'documents');
        foreach ($texts['lists.documents'] as $item) {
            $this->assertStringContainsString(htmlspecialchars($item, ENT_QUOTES), $documents);
        }
    }

    public function test_info_empty_required_sections_show_pending(): void
    {
        $app = $this->withTexts([
            'lists.packing' => [], 'lists.documents' => [], 'info.contraindications' => '', 'info.medicines' => '',
            'info.transfer' => '  ', 'info.contact' => '', 'info.visits' => '', 'info.prepare' => '', 'rules' => '',
        ]);
        $body = TestApp::get($app, '/info/')->body();
        foreach (['prepare', 'packing', 'documents', 'contraindications', 'medicines', 'contact', 'transfer', 'visits', 'rules'] as $id) {
            $this->assertStringContainsString('Информация обновляется', $this->section($body, $id), '#' . $id);
        }
    }

    public function test_info_shows_full_faq_and_visits_summary_links_to_rules(): void
    {
        $app = TestApp::make();
        $body = TestApp::get($app, '/info/')->body();
        $faq = $this->section($body, 'faq');
        foreach ($app->store()->read('faq')['items'] as $q) {
            $this->assertStringContainsString(htmlspecialchars($q['question'], ENT_QUOTES), $faq);
        }
        $this->assertStringContainsString('href="/pravila/#poseshchenie"', $this->section($body, 'visits'));
        $this->assertStringContainsString('id="poseshchenie"', TestApp::get($app, '/pravila/')->body());
    }

    public function test_vacancies_lists_visible_and_offers_other(): void
    {
        $app = TestApp::make();
        $items = $app->store()->read('vacancies')['items'];
        $items[1]['hidden'] = true;
        $app->store()->write('vacancies', ['items' => $items], 'test');
        $body = TestApp::get($app, '/vacancies/')->body();
        $this->assertMatchesRegularExpression('#<h1[^>]*>\s*Вакансии\s*</h1>#u', $body);
        $this->assertStringContainsString('Вожатый (пример)', $body);
        $this->assertStringNotContainsString('Инструктор по туризму (пример)', $body);
        $this->assertStringContainsString('Школа вожатых', $body);
        $this->assertStringContainsString('name="position"', $body);
        $this->assertStringContainsString('<option value="Другое"', $body);
        $this->assertStringNotContainsString('Сейчас набор закрыт', $body);
    }

    public function test_vacancies_empty_text(): void
    {
        $app = TestApp::make();
        $app->store()->write('vacancies', ['items' => []], 'test');
        $body = TestApp::get($app, '/vacancies/')->body();
        $this->assertStringContainsString('Сейчас набор закрыт, оставьте анкету на следующий сезон', $body);
        preg_match_all('#<option value="([^"]*)"#u', $this->section($body, 'anketa'), $m);
        $this->assertSame(['', 'Другое'], $m[1]);
    }

    public function test_vacancy_errors_rerender_on_vacancies_page(): void
    {
        $app = TestApp::make();
        $tokens = $app->guardFields('vacancy');
        $res = TestApp::post($app, '/form/vacancy/', $tokens + ['fullName' => 'Пётр Петров']);
        $this->assertSame(422, $res->status());
        $this->assertMatchesRegularExpression('#<h1[^>]*>\s*Вакансии\s*</h1>#u', $res->body());
        $this->assertStringContainsString('value="Пётр Петров"', $res->body());
        $this->assertStringContainsString('name="_sig"', $res->body());
        $this->assertSame(404, TestApp::get($app, '/_forms/vacancy/')->status());
    }

    public function test_team_hides_hidden_sorted_and_grouped(): void
    {
        $app = TestApp::make();
        $items = $app->store()->read('team')['items'];
        foreach ($items as &$m) {
            if ($m['name'] === 'Смирнов Никита Андреевич') {
                $m['hidden'] = true;
            }
            if ($m['name'] === 'Егорова Светлана Алексеевна') {
                $m['order'] = 100; // тот же order, что у Тараканова из «педагогов», но группа другая
            }
            if ($m['name'] === 'Гордзилевская Лия Антоновна') {
                $m['order'] = 100; // с Егоровой в одной группе и с равным order: по имени
            }
        }
        unset($m);
        $app->store()->write('team', ['items' => array_reverse($items)], 'test');
        $body = TestApp::get($app, '/team/')->body();

        $this->assertStringNotContainsString('Смирнов Никита', $body);
        $headings = ['Руководство', 'Педагоги', 'Вожатые', 'Ещё в команде'];
        $last = -1;
        foreach ($headings as $h) {
            $pos = mb_strpos($body, '>' . $h . '<');
            $this->assertNotFalse($pos, $h);
            $this->assertGreaterThan($last, $pos, 'порядок групп: ' . $h);
            $last = $pos;
        }
        $this->assertLessThan(mb_strpos($body, 'Егорова Светлана'), mb_strpos($body, 'Гордзилевская Лия'), 'при равном order — по алфавиту');
        $this->assertLessThan(mb_strpos($body, 'Корнейчук'), mb_strpos($body, 'Лучников'), 'по order внутри группы');
        $this->assertStringContainsString('>ЕЛ<', $body, 'инициалы без фото');
    }

    public function test_contacts_page(): void
    {
        $body = TestApp::get(TestApp::make(), '/contacts/')->body();
        $this->assertMatchesRegularExpression('#<h1[^>]*>\s*Контакты\s*</h1>#u', $body);
        $this->assertStringContainsString('href="tel:+73422474347"', $body);
        $this->assertStringContainsString('+7 (342) 247-43-47', $body);
        $this->assertStringContainsString('href="https://wa.me/79991239300"', $body);
        $this->assertStringContainsString('href="mailto:2474347@bk.ru"', $body);
        $this->assertStringContainsString('г. Пермь, ул. Милиционера Власова, 37', $body);
        $this->assertStringContainsString('Пн–Пт 10:00–19:00', $body);
        $this->assertStringContainsString('Сб–Вс 12:00–17:00', $body);
        $this->assertStringContainsString('https://vk.com/grozacamp', $body);
        $this->assertStringContainsString('yandex.ru/maps', $body);
    }

    public function test_contacts_hides_empty_settings(): void
    {
        $app = TestApp::make();
        $app->store()->write('settings', ['email' => '', 'whatsapp' => ''], 'test');
        $body = TestApp::get($app, '/contacts/')->body();
        $this->assertStringNotContainsString('mailto:', $body);
        $this->assertStringNotContainsString('wa.me', $body);
        $this->assertStringContainsString('href="tel:+73422474347"', $body);
    }

    public function test_rules_and_policy_pages(): void
    {
        $app = TestApp::make();
        $texts = $app->store()->read('texts');
        $rules = TestApp::get($app, '/pravila/');
        $this->assertSame(200, $rules->status());
        $this->assertMatchesRegularExpression('#<h1[^>]*>\s*Правила пребывания в лагере\s*</h1>#u', $rules->body());
        $this->assertStringContainsString('В лагере запрещено', $rules->body());
        $this->assertStringContainsString('class="richtext', $rules->body());
        $this->assertStringContainsString('href="#poseshchenie"', $rules->body(), 'оглавление');
        $this->assertStringNotContainsString('Информация обновляется', $rules->body());

        $policy = TestApp::get($app, '/policy/');
        $this->assertSame(200, $policy->status());
        $this->assertMatchesRegularExpression('#<h1[^>]*>\s*Политика обработки персональных данных\s*</h1>#u', $policy->body());
        $this->assertStringContainsString(mb_substr(strip_tags($texts['policy']), 0, 40), strip_tags($policy->body()));
    }

    public function test_empty_rules_show_pending(): void
    {
        $body = TestApp::get($this->withTexts(['rules' => '']), '/pravila/')->body();
        $this->assertStringContainsString('Информация обновляется', $body);
    }

    public function test_404_has_useful_links(): void
    {
        $app = TestApp::make();
        foreach (['/nope/', '/program/no-such-shift/'] as $path) {
            $res = TestApp::get($app, $path);
            $this->assertSame(404, $res->status(), $path);
            $this->assertStringContainsString('href="/booking/"', $res->body());
            $this->assertStringContainsString('href="/contacts/"', $res->body());
            $this->assertStringContainsString('Такой страницы нет', $res->body());
            $this->assertStringContainsString('noindex', $res->body());
        }
    }

    public function test_stub_pages_are_gone(): void
    {
        $body = TestApp::get(TestApp::make(), '/info/')->body();
        $this->assertStringNotContainsString('Раздел наполняется', $body);
        $this->assertStringNotContainsString('Раздел наполняется', TestApp::get(TestApp::make(), '/vacancies/')->body());
    }
}
