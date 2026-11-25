<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Admin;

use Groza\App;
use Groza\Support\Csrf;
use Groza\Support\Session;
use Groza\Tests\Support\AdminHttp;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

final class TextsControllerTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        Session::reset();
        $this->app = TestApp::make();
        AdminHttp::user($this->app);
        AdminHttp::login($this->app);
    }

    protected function tearDown(): void
    {
        Session::reset();
    }

    /** @return array<string,mixed> */
    private function texts(): array
    {
        return $this->app->store()->read('texts');
    }

    private function hash(): string
    {
        return $this->app->store()->hash('texts');
    }

    /** @param array<string,mixed> $text @param array<string,mixed> $extra */
    private function save(string $group, array $text, array $extra = []): \Groza\Http\Response
    {
        return AdminHttp::postWithToken($this->app, "/admin/texts/$group/", ['_hash' => $this->hash(), 'text' => $text] + $extra);
    }

    /** @return list<string> */
    private function actions(): array
    {
        return array_column($this->app->audit()->recent(50), 'action');
    }

    public function test_group_page_lists_all_keys_of_the_group(): void
    {
        $res = AdminHttp::get($this->app, '/admin/texts/svedeniya/');

        $this->assertSame(200, $res->status());
        $body = $res->body();
        foreach (['sved.fullName', 'sved.mode', 'sved.addresses'] as $key) {
            $this->assertStringContainsString('id="' . $key . '"', $body, 'якорь для ссылок «Готовности»');
            $this->assertStringContainsString('name="text[' . $key . ']"', $body);
        }
        $this->assertStringNotContainsString('name="text[home.heroTitle]"', $body);
        $this->assertStringContainsString('п. 3.а приказа № 201', $body);
        $this->assertStringContainsString('<details', $body);
        $this->assertStringContainsString('полное и сокращенное (при наличии) наименование Организации отдыха', $body);
        $this->assertStringContainsString('name="_hash" value="' . $this->hash() . '"', $body);
        $this->assertStringContainsString('name="_csrf"', $body);
        $this->assertStringContainsString('data-draft', $body);
    }

    public function test_list_type_is_one_item_per_line(): void
    {
        $body = AdminHttp::get($this->app, '/admin/texts/home/')->body();
        $this->assertMatchesRegularExpression('#<textarea[^>]*name="text\[home\.included\]"[^>]*>[^<]+\n[^<]+</textarea>#u', $body);

        $this->save('home', ['home.included' => "Питание\n\n  Проживание  \nЭкскурсии\n"]);
        $this->assertSame(['Питание', 'Проживание', 'Экскурсии'], $this->texts()['home.included']);
    }

    public function test_unknown_group_is_404(): void
    {
        $this->assertSame(404, AdminHttp::get($this->app, '/admin/texts/nope/')->status());
        $this->assertSame(404, AdminHttp::postWithToken($this->app, '/admin/texts/nope/', ['text' => []])->status());
    }

    public function test_unknown_text_key_rejected(): void
    {
        $before = $this->hash();
        $res = $this->save('home', ['home.heroTitle' => 'Новый', 'evil.key' => 'x']);

        $this->assertSame(400, $res->status());
        $this->assertSame($before, $this->hash(), 'ничего не записано');
        $this->assertArrayNotHasKey('evil.key', $this->texts());
    }

    public function test_key_of_another_group_rejected(): void
    {
        $before = $this->hash();
        $this->assertSame(400, $this->save('home', ['policy' => '<p>x</p>'])->status());
        $this->assertSame($before, $this->hash());
    }

    public function test_save_plain_and_audit(): void
    {
        $res = $this->save('home', ['home.heroTitle' => "  Новый\nзаголовок  "]);

        $this->assertSame(303, $res->status());
        $this->assertSame('/admin/texts/home/?saved=1', $res->header('Location'));
        $this->assertSame('Новый заголовок', $this->texts()['home.heroTitle']);
        $last = $this->app->audit()->recent(1)[0];
        $this->assertSame('texts.update', $last['action']);
        $this->assertSame('home', $last['target']);
        $this->assertSame('olga', $last['actor']);
        $this->assertArrayHasKey('policy', $this->texts(), 'чужие ключи не потеряны');
    }

    public function test_richtext_sanitized_on_save(): void
    {
        $res = $this->save('info', ['info.prepare' => '<p onclick="x()">текст</p><script>alert(1)</script><b>жирный</b><a href="javascript:alert(2)">л</a>']);

        $this->assertSame(303, $res->status());
        $saved = $this->texts()['info.prepare'];
        foreach (['<script', 'onclick', 'javascript:', '<b>'] as $bad) {
            $this->assertStringNotContainsString($bad, $saved);
        }
        $this->assertStringContainsString('<p>текст</p>', $saved);
        $this->assertStringContainsString('<strong>жирный</strong>', $saved);
    }

    public function test_plain_textarea_text_becomes_paragraphs_in_richtext(): void
    {
        $this->save('info', ['info.visits' => "Первый абзац\n\nВторой\nстрока"]);
        $this->assertSame('<p>Первый абзац</p><p>Второй<br />строка</p>', $this->texts()['info.visits']);
    }

    public function test_clearing_required_needs_confirm(): void
    {
        $this->assertNotSame('', $this->texts()['sved.fullName']);
        $before = $this->texts()['sved.fullName'];

        $res = $this->save('svedeniya', ['sved.fullName' => '   ', 'sved.director' => 'Новый Директор']);
        $this->assertSame(422, $res->status());
        $this->assertSame($before, $this->texts()['sved.fullName'], 'не сохранено');
        $this->assertNotSame('Новый Директор', $this->texts()['sved.director'], 'остальное тоже не сохранено, пока не решено');
        $body = $res->body();
        $this->assertStringContainsString('Это обязательный пункт для проверки (п. 3.а приказа № 201). Сохранить пустым?', $body);
        $this->assertStringContainsString('name="confirm_empty[sved.fullName]"', $body);
        $this->assertStringContainsString('Новый Директор', $body, 'введённое сохранено в форме');
        $this->assertStringContainsString('data-draft-unsaved', $body);

        $ok = $this->save('svedeniya', ['sved.fullName' => '', 'sved.director' => 'Новый Директор'], ['confirm_empty' => ['sved.fullName' => '1']]);
        $this->assertSame(303, $ok->status());
        $this->assertSame('', $this->texts()['sved.fullName']);
        $this->assertSame('Новый Директор', $this->texts()['sved.director']);
    }

    public function test_confirm_for_other_key_does_not_cover_this_one(): void
    {
        $res = $this->save('svedeniya', ['sved.fullName' => ''], ['confirm_empty' => ['sved.director' => '1']]);
        $this->assertSame(422, $res->status());
    }

    public function test_required_richtext_blank_after_tags_counts_as_empty(): void
    {
        foreach (['<p> </p>', '<br>', '<p>&nbsp;</p>'] as $blank) {
            $res = $this->save('info', ['rules' => $blank]);
            $this->assertSame(422, $res->status(), $blank);
            $this->assertStringContainsString('п. 4.б приказа № 201', $res->body());
        }
        $this->assertNotSame('', $this->texts()['rules']);
    }

    public function test_required_list_empty_needs_confirm(): void
    {
        $res = $this->save('info', ['lists.packing' => " \n \n"]);
        $this->assertSame(422, $res->status());
        $this->assertStringContainsString('п. 10.д приказа № 201', $res->body());
    }

    public function test_already_empty_required_does_not_block_other_changes(): void
    {
        $texts = $this->texts();
        $texts['sved.fullName'] = '';
        $this->app->store()->write('texts', $texts, 'test');

        $res = $this->save('svedeniya', ['sved.fullName' => '', 'sved.director' => 'Другой']);
        $this->assertSame(303, $res->status());
        $this->assertSame('Другой', $this->texts()['sved.director']);
    }

    public function test_optional_text_can_be_cleared_without_confirm(): void
    {
        $this->assertSame(303, $this->save('home', ['home.heroKicker' => ''])->status());
        $this->assertSame('', $this->texts()['home.heroKicker']);
    }

    public function test_conflict_409_keeps_typed_values(): void
    {
        $stale = $this->hash();
        $texts = $this->texts();
        $texts['home.heroLead'] = 'кто-то сохранил раньше';
        $this->app->store()->write('texts', $texts, 'other');

        $res = AdminHttp::postWithToken($this->app, '/admin/texts/home/', ['_hash' => $stale, 'text' => ['home.heroTitle' => 'Мой заголовок']]);

        $this->assertSame(409, $res->status());
        $this->assertStringContainsString('кто-то сохранил', $res->body());
        $this->assertStringContainsString('Мой заголовок', $res->body());
        $this->assertStringContainsString('name="_hash" value="' . $this->hash() . '"', $res->body(), 'свежий хеш для осознанного повтора');
        $this->assertNotSame('Мой заголовок', $this->texts()['home.heroTitle']);
        $this->assertSame('кто-то сохранил раньше', $this->texts()['home.heroLead']);
    }

    public function test_validation_error_422_keeps_hash_and_values(): void
    {
        $posted = $this->hash();
        $res = $this->save('svedeniya', ['sved.fullName' => '', 'sved.director' => 'Введённое <b>']);
        $this->assertSame(422, $res->status());
        $this->assertStringContainsString('name="_hash" value="' . $posted . '"', $res->body());
        $this->assertStringContainsString('Введённое &lt;b&gt;', $res->body());
    }

    public function test_expired_csrf_keeps_typed_text(): void
    {
        $typed = 'Набранное полчаса <script>alert(1)</script> & "кавычки"';
        $before = $this->hash();

        $res = AdminHttp::post($this->app, '/admin/texts/info/', ['_csrf' => 'stale', '_hash' => $before, 'text' => ['info.prepare' => $typed, 'info.visits' => 'Вторая правка']]);

        $this->assertSame(419, $res->status());
        $body = $res->body();
        $this->assertStringContainsString('Скопируйте текст, войдите заново и вставьте его', $body);
        $this->assertStringContainsString('Набранное полчаса &lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;кавычки&quot;', $body);
        $this->assertStringContainsString('Вторая правка', $body);
        $this->assertStringContainsString('readonly', $body);
        $this->assertStringNotContainsString('<script>alert(1)', $body);
        $this->assertStringNotContainsString($before, $body, '_hash не показываем');
        $this->assertStringNotContainsString('stale', $body, '_csrf не показываем');
        $this->assertSame($before, $this->hash(), 'не сохранено');
        $this->assertStringContainsString('Как подготовить ребёнка', $body, 'подпись поля из реестра текстов');
    }

    public function test_expired_csrf_logged_out_user_also_gets_typed_text_and_login_link(): void
    {
        AdminHttp::postWithToken($this->app, '/admin/logout/');
        $res = AdminHttp::post($this->app, '/admin/texts/info/', ['text' => ['info.prepare' => 'Мой текст']]);

        $this->assertSame(419, $res->status());
        $this->assertStringContainsString('Мой текст', $res->body());
        $this->assertStringContainsString('href="/admin/login/?next=/admin/texts/info/"', $res->body());
    }

    public function test_expired_csrf_never_echoes_passwords_and_caps_size(): void
    {
        $res = AdminHttp::post($this->app, '/admin/login/', ['login' => 'olga', 'password' => 'секретный-пароль-123', 'next' => '/admin/']);
        $this->assertSame(419, $res->status());
        $this->assertStringNotContainsString('секретный-пароль-123', $res->body());

        $res = AdminHttp::post($this->app, '/admin/password/', ['current' => 'старый-пароль-1', 'new' => 'новый-пароль-22', 'repeat' => 'новый-пароль-22']);
        $this->assertStringNotContainsString('старый-пароль-1', $res->body());
        $this->assertStringNotContainsString('новый-пароль-22', $res->body());

        $huge = str_repeat('Ж', 150000);
        $res = AdminHttp::post($this->app, '/admin/texts/info/', ['text' => ['info.prepare' => $huge]]);
        $this->assertSame(419, $res->status());
        $this->assertLessThan(120000 * 2, strlen($res->body()));
        $this->assertStringContainsString('не поместилась', $res->body());
    }

    public function test_expired_csrf_works_for_settings_and_collection_editor(): void
    {
        $res = AdminHttp::post($this->app, '/admin/settings/', ['settings' => ['officeAddress' => 'г. Пермь, новый адрес']]);
        $this->assertSame(419, $res->status());
        $this->assertStringContainsString('г. Пермь, новый адрес', $res->body());

        $shift = $this->app->store()->read('shifts')['items'][0];
        $res = AdminHttp::post($this->app, '/admin/shifts/' . $shift['id'] . '/', ['title' => 'Моё название', 'body' => '<p>Длинное описание</p>']);
        $this->assertSame(419, $res->status());
        $this->assertStringContainsString('Моё название', $res->body());
        $this->assertStringContainsString('&lt;p&gt;Длинное описание&lt;/p&gt;', $res->body());
    }

    public function test_csrf_required_for_texts_post(): void
    {
        $before = $this->hash();
        $this->assertSame(419, AdminHttp::post($this->app, '/admin/texts/home/', ['_hash' => $before, 'text' => ['home.heroTitle' => 'X']])->status());
        $this->assertSame($before, $this->hash());
        $this->assertNotContains('texts.update', $this->actions());
    }

    public function test_requires_login(): void
    {
        AdminHttp::postWithToken($this->app, '/admin/logout/');
        $res = AdminHttp::get($this->app, '/admin/texts/home/');
        $this->assertSame(302, $res->status());
        $this->assertStringContainsString('/admin/login/', (string) $res->header('Location'));
    }

    public function test_dashboard_links_to_every_group_and_settings(): void
    {
        $body = AdminHttp::get($this->app, '/admin/')->body();
        foreach (['home', 'info', 'svedeniya', 'vacancies', 'legal'] as $group) {
            $this->assertStringContainsString('href="/admin/texts/' . $group . '/"', $body);
        }
        $this->assertStringContainsString('href="/admin/settings/"', $body);
    }

    public function test_saved_banner_and_texts_page_has_no_inline_script_or_style(): void
    {
        $body = AdminHttp::get($this->app, '/admin/texts/info/', ['saved' => '1'])->body();
        $this->assertStringContainsString('Сохранено', $body);
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\ssrc=)/i', $body);
        $this->assertStringNotContainsString(' style=', $body);
        $this->assertStringNotContainsString('<style', $body);
        $this->assertStringContainsString('admin-editor.js', $body);
    }

    public function test_csrf_token_present(): void
    {
        $this->assertStringContainsString(Csrf::token(), AdminHttp::get($this->app, '/admin/texts/home/')->body());
    }
}
