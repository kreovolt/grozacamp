<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Admin;

use Groza\App;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Support\Csrf;
use Groza\Support\Session;
use Groza\Tests\Support\AdminHttp;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/**
 * Связи между записями не рвутся молча: адрес базы задаётся один раз (по нему смены знают базу), базу со сменами
 * не удалить, а смена после смены адреса доступна и по прежнему (ссылки из ВК).
 */
final class ReferencesTest extends TestCase
{
    private const VENUE = 'c55a7f906af5'; // solnyshko: на ней идут смены
    private const SHIFT = '832c0a011518'; // spasatel-2027

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
    private function item(string $name, string $id): array
    {
        foreach ($this->app->store()->read($name)['items'] as $i) {
            if ($i['id'] === $id) {
                return $i;
            }
        }
        $this->fail("нет $name/$id");
    }

    private function save(string $name, string $id, array $post): Response
    {
        return $this->app->handle(Request::create('POST', "/admin/$name/$id/", [], ['_csrf' => Csrf::token(), '_hash' => $this->app->store()->hash($name), 'confirm_readiness' => '1'] + $post));
    }

    public function test_venue_slug_is_set_once(): void
    {
        $venue = $this->item('venues', self::VENUE);
        $form = AdminHttp::get($this->app, '/admin/venues/' . self::VENUE . '/')->body();
        $this->assertMatchesRegularExpression('#<input type="text" id="f-slug" name="slug" value="solnyshko"[^>]*readonly#', $form);

        $res = $this->save('venues', self::VENUE, ['slug' => 'drugaya', 'name' => $venue['name']]);
        $this->assertSame(303, $res->status());
        $this->assertSame('solnyshko', $this->item('venues', self::VENUE)['slug'], 'присланный адрес не принят');

        $new = AdminHttp::get($this->app, '/admin/venues/new/')->body();
        $this->assertDoesNotMatchRegularExpression('#id="f-slug"[^>]*readonly#', $new, 'у новой базы адрес вводится');
        $this->assertSame(303, $this->save('venues', 'new', ['slug' => 'novaya-baza', 'name' => 'Новая база'])->status());
        $this->assertContains('novaya-baza', array_column($this->app->store()->read('venues')['items'], 'slug'));
    }

    public function test_venue_used_by_shifts_cannot_be_deleted(): void
    {
        $before = $this->app->store()->hash('venues');
        $res = AdminHttp::postWithToken($this->app, '/admin/venues/' . self::VENUE . '/delete/', ['confirm' => '1', 'confirm_readiness' => '1']);

        $this->assertSame(422, $res->status());
        $this->assertStringContainsString('Эту запись нельзя удалить', $res->body());
        $this->assertStringContainsString('Спасатель', $res->body());
        $this->assertSame($before, $this->app->store()->hash('venues'));

        // база без смен удаляется как обычно
        $this->save('venues', 'new', ['slug' => 'pustaya', 'name' => 'Пустая база']);
        $id = array_column($this->app->store()->read('venues')['items'], 'id', 'slug')['pustaya'];
        $this->assertSame(303, AdminHttp::postWithToken($this->app, '/admin/venues/' . $id . '/delete/', ['confirm' => '1'])->status());
    }

    public function test_old_shift_address_redirects_to_the_new_one(): void
    {
        $shift = $this->item('shifts', self::SHIFT);
        $post = [
            'title' => $shift['title'], 'slug' => 'spasatel-leto-2027', 'dateStart' => $shift['dateStart'], 'dateEnd' => $shift['dateEnd'],
            'ageMin' => (string) $shift['ageMin'], 'ageMax' => (string) $shift['ageMax'], 'status' => $shift['status'], 'venue' => $shift['venue'],
            'price' => (string) ($shift['price'] ?? ''),
        ];
        $this->assertSame(303, $this->save('shifts', self::SHIFT, $post)->status());
        $this->assertSame(['spasatel-2027'], $this->item('shifts', self::SHIFT)['formerSlugs']);

        $res = TestApp::get($this->app, '/program/spasatel-2027/');
        $this->assertSame(301, $res->status());
        $this->assertSame('/program/spasatel-leto-2027/', $res->header('Location'));
        $this->assertSame(200, TestApp::get($this->app, '/program/spasatel-leto-2027/')->status());

        // ещё раз переименовали — работают оба прежних адреса; вернули прежний — он уже не «прежний»
        $this->save('shifts', self::SHIFT, ['slug' => 'spasatel-3'] + $post);
        $this->assertSame(['spasatel-2027', 'spasatel-leto-2027'], $this->item('shifts', self::SHIFT)['formerSlugs']);
        $this->assertSame('/program/spasatel-3/', TestApp::get($this->app, '/program/spasatel-2027/')->header('Location'));
        $this->save('shifts', self::SHIFT, ['slug' => 'spasatel-2027'] + $post);
        $this->assertSame(['spasatel-leto-2027', 'spasatel-3'], $this->item('shifts', self::SHIFT)['formerSlugs']);
        $this->assertSame(200, TestApp::get($this->app, '/program/spasatel-2027/')->status());

        // скрытая смена по прежнему адресу не находится
        $this->save('shifts', self::SHIFT, ['hidden' => '1'] + ['slug' => 'spasatel-2027'] + $post);
        $this->assertSame(404, TestApp::get($this->app, '/program/spasatel-3/')->status());
    }
}
