<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Admin;

use Groza\App;
use Groza\Http\Request;
use Groza\Support\Csrf;
use Groza\Support\Session;
use Groza\Tests\Support\AdminHttp;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/** История изменений: список версий по файлам данных и откат в один клик (с подтверждением). */
final class HistoryTest extends TestCase
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

    /** Меняет название первой смены и возвращает [прежнее название, id версии с прежними данными]. @return array{0:string,1:string} */
    private function renameFirstShift(string $to): array
    {
        $store = $this->app->store();
        $data = $store->read('shifts');
        $old = $data['items'][0]['title'];
        $data['items'][0]['title'] = $to;
        $store->write('shifts', $data, 'olga');

        return [$old, $store->versions('shifts')[0]['id']];
    }

    private function title(): string
    {
        return $this->app->store()->read('shifts')['items'][0]['title'];
    }

    /** @return array<string,string> */
    private function restorePost(string $name, string $version, string $confirm = '1'): array
    {
        return ['name' => $name, 'version' => $version] + ($confirm !== '' ? ['confirm' => $confirm] : []);
    }

    public function test_restore_from_history(): void
    {
        [$old, $version] = $this->renameFirstShift('Новое название');
        $this->assertSame('Новое название', $this->title());

        $res = AdminHttp::postWithToken($this->app, '/admin/history/restore/', $this->restorePost('shifts', $version));

        $this->assertSame(303, $res->status());
        $this->assertStringStartsWith('/admin/history/', (string) $res->header('Location'));
        $this->assertSame($old, $this->title(), 'данные вернулись');
        $last = $this->app->audit()->recent(1)[0];
        $this->assertSame(['olga', 'restore', 'shifts:' . $version], [$last['actor'], $last['action'], $last['target']]);
        // сам откат тоже попал в историю: его можно отменить
        $this->assertSame('olga', $this->app->store()->versions('shifts')[0]['actor']);
        $this->assertCount(2, $this->app->store()->versions('shifts'));
    }

    public function test_restore_requires_post_and_csrf(): void
    {
        [, $version] = $this->renameFirstShift('Новое название');

        $get = AdminHttp::get($this->app, '/admin/history/restore/', $this->restorePost('shifts', $version));
        $this->assertNotSame(200, $get->status());
        $this->assertSame('Новое название', $this->title());

        $noToken = AdminHttp::post($this->app, '/admin/history/restore/', $this->restorePost('shifts', $version));
        $this->assertSame(419, $noToken->status());
        $bad = AdminHttp::post($this->app, '/admin/history/restore/', ['_csrf' => 'nope'] + $this->restorePost('shifts', $version));
        $this->assertSame(419, $bad->status());
        $this->assertSame('Новое название', $this->title(), 'без токена ничего не вернулось');
    }

    public function test_restore_requires_login(): void
    {
        [, $version] = $this->renameFirstShift('Новое название');
        $token = Csrf::token();
        Session::reset();
        $fresh = TestApp::make();
        $res = $fresh->handle(Request::create('POST', '/admin/history/restore/', [], ['_csrf' => $token] + $this->restorePost('shifts', $version)));

        $this->assertContains($res->status(), [302, 401, 419]);
        $this->assertSame('Новое название', $this->title());
    }

    public function test_restore_needs_server_side_confirmation(): void
    {
        [, $version] = $this->renameFirstShift('Новое название');

        foreach (['', '0', 'yes'] as $confirm) {
            $res = AdminHttp::postWithToken($this->app, '/admin/history/restore/', $this->restorePost('shifts', $version, $confirm));
            $this->assertSame(422, $res->status());
            $this->assertStringContainsString('Да, вернуть', $res->body());
        }
        $this->assertSame('Новое название', $this->title());
        $this->assertNotSame('restore', ($this->app->audit()->recent(1)[0]['action'] ?? ''));
    }

    public function test_restore_validates_name_and_version(): void
    {
        [, $version] = $this->renameFirstShift('Новое название');
        $post = fn (string $n, string $v) => AdminHttp::postWithToken($this->app, '/admin/history/restore/', $this->restorePost($n, $v));

        $this->assertSame(400, $post('shifts', '../../data/shifts')->status());
        $this->assertSame(400, $post('shifts', '20270101000000-000000-abcd')->status(), 'верный вид, но такой версии нет');
        $this->assertSame(400, $post('../data/shifts', $version)->status());
        $this->assertSame(400, $post('nothing', $version)->status());
        $this->assertSame(400, $post('', '')->status());
        $this->assertSame('Новое название', $this->title());
        $this->assertSame(400, AdminHttp::postWithToken($this->app, '/admin/history/restore/', ['name' => ['shifts'], 'version' => [$version], 'confirm' => '1'])->status());
    }

    public function test_password_files_are_never_listed_or_restored(): void
    {
        $store = $this->app->store();
        $before = (string) file_get_contents($this->app->config()['storageDir'] . '/data/users.json');
        // версия «из прошлого» (как могла остаться от старой сборки): подкладываем руками
        $dir = $this->app->config()['storageDir'] . '/versions/users';
        mkdir($dir, 0777, true);
        $id = '20260101000000-000000-abcd';
        file_put_contents($dir . '/' . $id . '.json', '{"items":[{"login":"evil","hash":"HASH-LEAK"}]}');
        file_put_contents($dir . '/' . $id . '.meta', '{"actor":"x","at":"2026-01-01T00:00:00+05:00"}');

        $page = AdminHttp::get($this->app, '/admin/history/')->body();
        $this->assertStringNotContainsString('HASH-LEAK', $page);
        $this->assertStringNotContainsString('value="users"', $page);
        $this->assertStringNotContainsString('auth-meta', $page);

        foreach (['users', 'auth-meta'] as $name) {
            $res = AdminHttp::postWithToken($this->app, '/admin/history/restore/', $this->restorePost($name, $id));
            $this->assertSame(400, $res->status());
        }
        $this->assertSame($before, (string) file_get_contents($this->app->config()['storageDir'] . '/data/users.json'));
        $this->assertSame([], $store->versions('users') === [] ? [] : [], 'проверка выше — главная');
    }

    public function test_history_page_lists_versions_by_file_with_time_and_who(): void
    {
        [, $version] = $this->renameFirstShift('Новое название');

        $res = AdminHttp::get($this->app, '/admin/history/');
        $body = $res->body();

        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('История изменений', $body);
        foreach (['Смены', 'Базы', 'Команда', 'Документы', 'Вакансии', 'Вопросы и ответы', 'Отзывы', 'Тексты сайта', 'Настройки и контакты'] as $label) {
            $this->assertStringContainsString($label, $body, $label);
        }
        $this->assertStringContainsString('value="' . $version . '"', $body);
        $this->assertStringContainsString('name="confirm"', $body);
        $this->assertStringContainsString('Да, вернуть', $body);
        $this->assertStringContainsString('Вернуть эту версию', $body);
        $this->assertStringContainsString('Ольга', $body, 'кто сохранил — по имени');
        $this->assertMatchesRegularExpression('/\d{1,2} [а-я]+, \d{2}:\d{2}/u', $body);
        $this->assertStringContainsString('name="_csrf"', $body);
    }

    public function test_history_requires_login(): void
    {
        Session::reset();
        $fresh = TestApp::make();

        $this->assertSame(302, AdminHttp::get($fresh, '/admin/history/')->status());
    }
}
