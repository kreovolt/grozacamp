<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Admin;

use Groza\App;
use Groza\Support\Session;
use Groza\Tests\Support\AdminHttp;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/** Главная админки: «Готовность к проверке», последние изменения, плитки разделов. «Сегодня» — 2027-01-15, Пермь. */
final class DashboardTest extends TestCase
{
    private const COMPLETE = __DIR__ . '/../../../fixtures/complete';

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

    /** Заполненный комплект данных из tests/fixtures/complete — в хранилище. */
    private function useComplete(): void
    {
        foreach (['settings', 'texts', 'shifts', 'venues', 'team', 'documents'] as $name) {
            $this->app->store()->write($name, json_decode((string) file_get_contents(self::COMPLETE . "/$name.json"), true, 512, JSON_THROW_ON_ERROR), 'test');
        }
    }

    private function page(): string
    {
        $res = AdminHttp::get($this->app, '/admin/');
        $this->assertSame(200, $res->status());

        return $res->body();
    }

    public function test_dashboard_red_item_links_to_fix(): void
    {
        $this->useComplete();
        $store = $this->app->store();
        $team = $store->read('team');
        $items = $team['items'] ?? $team;
        $target = null;
        foreach ($items as $i => $m) {
            if (($m['group'] ?? '') === 'pedagogue' && $target === null) {
                $items[$i]['education'] = '';
                $target = $m['id'];
            }
        }
        $this->assertNotNull($target, 'в образце есть педагог');
        $store->write('team', isset($team['items']) ? ['items' => $items] : $items, 'test');

        $body = $this->page();

        $this->assertStringContainsString('Готовность к проверке', $body);
        $this->assertStringContainsString('Не заполнено: 1 обязательный пункт', $body);
        $this->assertStringContainsString('class="readiness__summary readiness__summary--bad"', $body);
        $this->assertMatchesRegularExpression('#<a [^>]*href="/admin/team/' . $target . '/"[^>]*>Заполнить#u', $body);
        $this->assertStringContainsString('Педагогический и вожатский состав', $body);
    }

    public function test_dashboard_ready_is_green(): void
    {
        $this->useComplete();
        $body = $this->page();

        $this->assertStringContainsString('Готово к проверке', $body);
        $this->assertStringContainsString('readiness__summary--ok', $body);
        $this->assertStringNotContainsString('Не заполнено', $body);
        $this->assertStringNotContainsString('>Заполнить<', $body);
    }

    public function test_dashboard_on_demo_data_is_red_with_sections_in_order_201_order(): void
    {
        $body = $this->page();

        $this->assertStringContainsString('readiness__summary--bad', $body);
        $this->assertMatchesRegularExpression('/Не заполнено: \d+ обязательных? пункт/u', $body);
        $last = -1;
        $seen = 0;
        foreach (['Основные сведения', 'Документы', 'Руководство', 'Педагогический и вожатский состав', 'Контакты', 'Деятельность', 'Материально-техническое обеспечение', 'Услуги, в том числе платные', 'Доступная среда'] as $title) {
            $pos = strpos($body, '<h3 class="readiness__section">' . $title);
            if ($pos === false) {
                continue; // раздел может быть уже заполнен в примерных данных
            }
            $this->assertGreaterThan($last, $pos, "«{$title}» стоит раньше предыдущего раздела");
            $last = $pos;
            $seen++;
        }
        $this->assertGreaterThan(0, $seen);
        $this->assertStringContainsString('>Заполнить</a>', $body);
    }

    public function test_dashboard_warnings_follow_required_items_and_do_not_block(): void
    {
        $this->useComplete();
        $shifts = $this->app->store()->read('shifts');
        $items = array_map(static fn (array $s): array => ['status' => 'closed'] + $s, $shifts['items'] ?? $shifts);
        $this->app->store()->write('shifts', ['items' => $items], 'test');

        $body = $this->page();

        $this->assertStringContainsString('Готово к проверке', $body);
        $this->assertStringContainsString('class="readiness__warnings"', $body);
        $this->assertStringContainsString('Нет предстоящих смен с открытым набором', $body);
        $this->assertLessThan(strpos($body, 'Последние изменения'), strpos($body, 'readiness__warnings'));
    }

    public function test_dashboard_recent_changes_in_russian_with_record_title(): void
    {
        $shift = $this->app->store()->read('shifts')['items'][0];
        AdminHttp::postWithToken($this->app, '/admin/shifts/' . $shift['id'] . '/status/', ['status' => 'closed']);

        $body = $this->page();

        $this->assertStringContainsString('Последние изменения', $body);
        $this->assertStringContainsString('Ольга', $body, 'кто');
        $this->assertStringContainsString('изменил(а) статус смены', $body, 'что — по-русски, не код действия');
        $this->assertStringContainsString($shift['title'], $body, 'запись названа по заголовку');
        $this->assertStringNotContainsString('shifts.status', $body);
        $this->assertMatchesRegularExpression('/\d{1,2} [а-я]+, \d{2}:\d{2}/u', $body, 'когда: «25 ноября, 14:05»');
    }

    public function test_dashboard_recent_changes_survive_deleted_records_and_unknown_actions(): void
    {
        $this->app->audit()->record('olga', 'shifts.update', 'ffffffffffff');
        $this->app->audit()->record('olga', 'mystery.thing', 'x');
        $this->app->audit()->record('olga', 'texts.update', 'svedeniya');
        $this->app->audit()->record('olga', 'settings.update', 'settings');
        $this->app->audit()->record('olga', 'restore', 'shifts:20270101000000-000000-abcd');
        $this->app->audit()->record('olga', 'backup.download', '-');

        $body = $this->page();

        $this->assertStringContainsString('изменил(а) смену', $body);
        $this->assertStringContainsString('mystery.thing', $body, 'неизвестное действие показывается как есть');
        $this->assertStringContainsString('изменил(а) тексты', $body);
        $this->assertStringContainsString('изменил(а) настройки', $body);
        $this->assertStringContainsString('вернул(а) прежнюю версию', $body);
        $this->assertStringContainsString('скачал(а) резервную копию', $body);
    }

    public function test_dashboard_hides_login_noise_and_escapes(): void
    {
        $this->app->audit()->record('<b>x</b>', 'shifts.delete', 'abc');

        $body = $this->page();

        $this->assertStringNotContainsString('auth.login.ok', $body);
        $this->assertStringNotContainsString('<b>x</b>', $body);
    }

    public function test_dashboard_links_to_history_and_backup(): void
    {
        $body = $this->page();

        $this->assertStringContainsString('href="/admin/history/"', $body);
        $this->assertStringContainsString('href="/admin/backup/"', $body);
    }

    public function test_dashboard_requires_login(): void
    {
        Session::reset();
        $fresh = TestApp::make();

        $this->assertSame(302, AdminHttp::get($fresh, '/admin/')->status());
    }
}
