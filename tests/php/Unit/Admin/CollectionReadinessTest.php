<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Admin;

use Groza\App;
use Groza\Domain\Collections;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Readiness\Readiness;
use Groza\Support\Csrf;
use Groza\Support\Session;
use Groza\Tests\Support\AdminHttp;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/**
 * Спека §10.4: после запуска при очистке обязательного поля админка предупреждает перед сохранением.
 * Редактор списков сравнивает «Готовность к проверке» до и после изменения (сохранение, удаление, скрытие);
 * если какой-то обязательный пункт приказа перестанет выполняться — ничего не пишет и спрашивает.
 */
final class CollectionReadinessTest extends TestCase
{
    private const VENUE = 'c55a7f906af5';        // ДЗОЛ «Солнышко»: на ней идут смены сезона
    private const PROGRAMMA = '9e962b16100c';    // единственная «Программа воспитательной работы»
    private const LEADERS = ['a8bd8cc34880', '4c6d259f2ab1'];
    private const TAIL = ' — сайт перестанет соответствовать приказу. Сохранить всё равно?';

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

    /** Форма записи так, как её отправил бы браузер (простые поля; галерея и файлы остаются как есть). @return array<string,mixed> */
    private function form(string $name, string $id, array $over = []): array
    {
        $item = $this->item($name, $id);
        $post = [];
        foreach (Collections::get($name)['fields'] as $f) {
            $n = $f['name'];
            if (in_array($f['type'], ['gallery', 'file', 'image'], true) || !empty($f['computed'])) {
                continue;
            }
            $v = $item[$n] ?? null;
            $post[$n] = match ($f['type']) {
                'bool' => !empty($v) ? '1' : '0',
                'list' => is_array($v) ? implode("\n", $v) : '',
                default => is_scalar($v) ? (string) $v : '',
            };
        }

        return $over + $post;
    }

    private function save(string $name, string $id, array $post): Response
    {
        return $this->app->handle(Request::create('POST', "/admin/$name/$id/", [], ['_csrf' => Csrf::token(), '_hash' => $this->app->store()->hash($name)] + $post));
    }

    private function post(string $path, array $post): Response
    {
        return $this->app->handle(Request::create('POST', $path, [], ['_csrf' => Csrf::token()] + $post));
    }

    /** Все педагоги и вожатые с должностью и образованием: п. 6 а выполнен. */
    private function fillStaff(): void
    {
        $team = $this->app->store()->read('team');
        foreach ($team['items'] as $i => $m) {
            $team['items'][$i]['education'] = $m['education'] ?? '' ?: 'Высшее';
            $team['items'][$i]['role'] = $m['role'] ?? '' ?: 'Вожатый';
        }
        $this->app->store()->write('team', $team, 'test');
    }

    private function ok(string $readinessId): bool
    {
        foreach (Readiness::evaluate(Readiness::dataFrom($this->app), $this->app->clock()->now()) as $i) {
            if ($i['id'] === $readinessId) {
                return $i['ok'];
            }
        }
        $this->fail("нет пункта $readinessId");
    }

    public function test_clearing_a_venue_field_asks_before_saving_and_keeps_the_input(): void
    {
        $this->assertTrue($this->ok('mto.b:solnyshko'));
        $hash = $this->app->store()->hash('venues');
        $post = $this->form('venues', self::VENUE, ['mto_b' => '', 'address' => 'Новый адрес, который я набрал']);

        $res = $this->save('venues', self::VENUE, $post);
        $this->assertSame(422, $res->status());
        $body = $res->body();
        $this->assertStringContainsString('После сохранения не будет заполнен п. 9 б) Условия проживания: ДЗОЛ «Солнышко»' . self::TAIL, html_entity_decode($body));
        $this->assertStringContainsString('name="confirm_readiness" value="1"', $body);
        $this->assertStringContainsString('Сохранить всё равно', $body);
        $this->assertStringContainsString('Новый адрес, который я набрал', $body, 'введённое остаётся в форме');
        $this->assertStringContainsString('name="_hash" value="' . $hash . '"', $body, 'тот же _hash: чужая правка между отправками даст 409');
        $this->assertSame($hash, $this->app->store()->hash('venues'), 'ничего не записано');

        $res = $this->save('venues', self::VENUE, $post + ['confirm_readiness' => '1']);
        $this->assertSame(303, $res->status());
        $this->assertSame('', (string) ($this->item('venues', self::VENUE)['mto_b'] ?? ''));
        $this->assertFalse($this->ok('mto.b:solnyshko'));
    }

    public function test_clearing_education_of_a_pedagogue_asks(): void
    {
        $this->fillStaff();
        $this->assertTrue($this->ok('sostav.a'));
        $id = '9436bb384ed3';

        $res = $this->save('team', $id, $this->form('team', $id, ['education' => '']));
        $this->assertSame(422, $res->status());
        $this->assertStringContainsString('После сохранения не будет заполнен п. 6 а) Состав работников: не заполнены должность или уровень образования у 1 из', html_entity_decode($res->body()));
        $this->assertNotSame('', $this->item('team', $id)['education']);
    }

    public function test_unrelated_changes_and_other_collections_save_without_a_question(): void
    {
        $res = $this->save('venues', self::VENUE, $this->form('venues', self::VENUE, ['address' => 'Пермский край, уточнённый адрес']));
        $this->assertSame(303, $res->status());

        $faq = $this->app->store()->read('faq')['items'][0];
        $res = $this->save('faq', $faq['id'], $this->form('faq', $faq['id'], ['question' => 'Уточнённый вопрос?']));
        $this->assertSame(303, $res->status());
    }

    public function test_already_failing_item_does_not_ask_again(): void
    {
        $this->assertFalse($this->ok('sostav.a')); // в примерных данных образование заполнено не у всех
        $id = '9436bb384ed3';
        $res = $this->save('team', $id, $this->form('team', $id, ['education' => '']));
        $this->assertSame(303, $res->status(), 'пункт и так не выполнен — новый вопрос ничего не добавляет');
    }

    public function test_new_shift_without_a_venue_asks(): void
    {
        $post = ['title' => 'Осенняя смена', 'dateStart' => '2027-10-01', 'dateEnd' => '2027-10-07', 'ageMin' => '8', 'ageMax' => '14', 'price' => '30000', 'status' => 'open'];
        $res = $this->save('shifts', 'new', $post);
        $this->assertSame(422, $res->status());
        $this->assertStringContainsString('п. 9 а) У смены «Осенняя смена» не выбрана база', html_entity_decode($res->body()));
        $this->assertCount(4, $this->app->store()->read('shifts')['items']);

        $this->assertSame(303, $this->save('shifts', 'new', $post + ['venue' => 'solnyshko'])->status());
    }

    public function test_deleting_the_only_document_of_a_required_section_asks(): void
    {
        $this->assertTrue($this->ok('dokumenty.v'));
        $path = '/admin/documents/' . self::PROGRAMMA . '/delete/';

        $res = $this->post($path, ['confirm' => '1']);
        $this->assertSame(422, $res->status());
        $body = html_entity_decode($res->body());
        $this->assertStringContainsString('После удаления не будет заполнен п. 4 в) Программа и календарный план воспитательной работы' . self::TAIL, $body);
        $this->assertStringContainsString('action="' . $path . '"', $body);
        $this->assertStringContainsString('name="confirm" value="1"', $body);
        $this->assertStringContainsString('name="confirm_readiness" value="1"', $body);
        $this->item('documents', self::PROGRAMMA); // на месте

        $res = $this->post($path, ['confirm' => '1', 'confirm_readiness' => '1']);
        $this->assertSame(303, $res->status());
        $this->assertNotContains(self::PROGRAMMA, array_column($this->app->store()->read('documents')['items'], 'id'));
        $this->assertFalse($this->ok('dokumenty.v'));
    }

    public function test_hiding_the_last_leader_asks(): void
    {
        $this->assertSame(303, $this->post('/admin/team/' . self::LEADERS[0] . '/visibility/', ['state' => 'hide'])->status(), 'второй руководитель ещё виден');

        $path = '/admin/team/' . self::LEADERS[1] . '/visibility/';
        $res = $this->post($path, ['state' => 'hide']);
        $this->assertSame(422, $res->status());
        $body = html_entity_decode($res->body());
        $this->assertStringContainsString('После изменения видимости не будет заполнен п. 5) Руководство', $body);
        $this->assertStringContainsString('name="state" value="hide"', $body);
        $this->assertTrue(empty($this->item('team', self::LEADERS[1])['hidden']), 'не скрыт');

        $this->assertSame(303, $this->post($path, ['state' => 'hide', 'confirm_readiness' => '1'])->status());
        $this->assertTrue($this->item('team', self::LEADERS[1])['hidden']);
        $this->assertFalse($this->ok('rukovodstvo.a'));
    }

    public function test_conflict_still_wins_over_the_question(): void
    {
        $post = $this->form('venues', self::VENUE, ['mto_b' => '']);
        $res = $this->app->handle(Request::create('POST', '/admin/venues/' . self::VENUE . '/', [], ['_csrf' => Csrf::token(), '_hash' => 'stale'] + $post));
        $this->assertSame(409, $res->status());
    }
}
