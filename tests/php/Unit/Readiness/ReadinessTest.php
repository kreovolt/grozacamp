<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Readiness;

use DateTimeImmutable;
use Groza\Readiness\Readiness;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/** Проверка готовности к проверке (Приказ № 201): «сегодня» — 2027-01-15, Пермь. */
final class ReadinessTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../fixtures/complete';

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2027-01-15T12:00:00+05:00');
    }

    /** @return array<string,mixed> */
    private function complete(): array
    {
        $data = [];
        foreach (['settings', 'texts', 'shifts', 'venues', 'team', 'documents'] as $name) {
            $data[$name] = json_decode((string) file_get_contents(self::FIXTURES . "/$name.json"), true, 512, JSON_THROW_ON_ERROR);
        }

        return $data;
    }

    /** @param list<array<string,mixed>> $items @return array<string,mixed> */
    private function byId(array $items, string $id): array
    {
        foreach ($items as $item) {
            if ($item['id'] === $id) {
                return $item;
            }
        }
        $this->fail("нет пункта $id среди: " . implode(', ', array_column($items, 'id')));
    }

    /** @param array<string,mixed> $data @return list<array<string,mixed>> */
    private function evaluate(array $data): array
    {
        return Readiness::evaluate($data, $this->now());
    }

    /** @param list<array<string,mixed>> $items @return list<string> */
    private function failedRequired(array $items): array
    {
        return array_values(array_map(
            static fn (array $i): string => $i['id'],
            array_filter($items, static fn (array $i): bool => $i['level'] === 'required' && !$i['ok'])
        ));
    }

    public function test_complete_fixture_is_ready(): void
    {
        $items = $this->evaluate($this->complete());

        $this->assertSame([], $this->failedRequired($items));
        $this->assertTrue(Readiness::isReady($items));
        foreach ($items as $item) {
            $this->assertSame(['id', 'section', 'label', 'ok', 'level', 'fix'], array_keys($item));
            $this->assertContains($item['level'], ['required', 'warning']);
            $this->assertNotSame('', $item['label']);
            $this->assertStringStartsWith('/admin/', $item['fix']);
        }
    }

    public function test_every_required_order_item_is_checked(): void
    {
        $ids = array_column($this->evaluate($this->complete()), 'id');
        $order = require __DIR__ . '/../../../../config/order201.php';

        $this->assertCount(44, $order);
        foreach ($order as $def) {
            if ($def['level'] !== 'required') {
                continue;
            }
            $perVenue = str_starts_with($def['id'], 'mto.') || str_starts_with($def['id'], 'dostupnaya.');
            $this->assertContains($perVenue ? $def['id'] . ':solnyshko' : $def['id'], $ids, $def['id']);
        }
        $this->assertNotContains('dostupnaya.e:solnyshko', $ids, 'п. 11 е — «при наличии»');
    }

    public function test_order_config_levels_and_wording(): void
    {
        $order = require __DIR__ . '/../../../../config/order201.php';
        $byId = array_column($order, null, 'id');

        $this->assertSame(count($order), count($byId), 'id не повторяются');
        foreach (['dokumenty.g', 'sostav.b', 'kontakty.b', 'deyatelnost.v', 'deyatelnost.g', 'deyatelnost.d', 'mto.d', 'uslugi.v', 'dostupnaya.e'] as $id) {
            $this->assertSame('optional', $byId[$id]['level'], $id);
        }
        foreach (['osnovnye.a', 'dokumenty.v', 'sostav.a', 'deyatelnost.b', 'uslugi.b', 'dostupnaya.n'] as $id) {
            $this->assertSame('required', $byId[$id]['level'], $id);
        }
        $this->assertSame('учредительные документы Организации отдыха', $byId['dokumenty.a']['full']);
        $this->assertSame(['а', 'б', 'в', 'г', 'д', 'е', 'ж', 'з', 'и', 'к', 'л', 'м', 'н'], array_map(
            static fn (array $d): string => explode(' ', $d['paragraph'])[1],
            array_values(array_filter($order, static fn (array $d): bool => str_starts_with($d['id'], 'dostupnaya.')))
        ));
    }

    public function test_demo_missing_education_flagged(): void
    {
        $app = TestApp::make();
        $items = Readiness::evaluate(Readiness::dataFrom($app), $app->clock()->now());

        $item = $this->byId($items, 'sostav.a');
        $this->assertFalse($item['ok']);
        $this->assertSame('required', $item['level']);
        $this->assertMatchesRegularExpression('#^/admin/team/[0-9a-f]{12}/$#', $item['fix']);
        $this->assertFalse(Readiness::isReady($items));
    }

    public function test_texts_settings_documents_fix_addresses(): void
    {
        $data = $this->complete();
        $data['texts']['sved.legalForm'] = '  ';
        $data['texts']['lists.packing'] = [];
        $data['settings']['phone'] = '';
        $data['documents']['items'] = array_values(array_filter(
            $data['documents']['items'],
            static fn (array $d): bool => $d['section'] !== 'uchreditelnye'
        ));
        $items = $this->evaluate($data);

        $this->assertSame('/admin/texts/svedeniya/#sved.legalForm', $this->byId($items, 'osnovnye.v')['fix']);
        $this->assertSame('/admin/texts/info/#lists.packing', $this->byId($items, 'uslugi.d')['fix']);
        $this->assertSame('/admin/settings/#phone', $this->byId($items, 'kontakty.a')['fix']);
        $this->assertSame('/admin/documents/new/?section=uchreditelnye', $this->byId($items, 'dokumenty.a')['fix']);
        $this->assertSame(['osnovnye.v', 'dokumenty.a', 'kontakty.a', 'uslugi.d'], $this->failedRequired($items));
    }

    public function test_document_without_file_does_not_count(): void
    {
        $data = $this->complete();
        $data['documents']['items'][0]['file'] = null;

        $this->assertContains('dokumenty.a', $this->failedRequired($this->evaluate($data)));
    }

    public function test_programma_and_calendar_plan_are_both_needed(): void
    {
        $data = $this->complete();
        $data['documents']['items'] = array_values(array_filter(
            $data['documents']['items'],
            static fn (array $d): bool => $d['section'] !== 'kalendarnyj-plan'
        ));
        $item = $this->byId($this->evaluate($data), 'dokumenty.v');

        $this->assertFalse($item['ok']);
        $this->assertSame('/admin/documents/new/?section=kalendarnyj-plan', $item['fix']);
    }

    public function test_rules_text_replaces_rules_scan(): void
    {
        $data = $this->complete();
        $this->assertTrue($this->byId($this->evaluate($data), 'dokumenty.b')['ok'], 'есть и текст, и скан');
        $data['documents']['items'] = array_values(array_filter($data['documents']['items'], static fn (array $d): bool => $d['section'] !== 'pravila'));
        $this->assertTrue($this->byId($this->evaluate($data), 'dokumenty.b')['ok'], 'достаточно текста правил (страница /pravila/)');
        $data['texts']['rules'] = '';
        $item = $this->byId($this->evaluate($data), 'dokumenty.b');
        $this->assertFalse($item['ok']);
        $this->assertSame('/admin/texts/info/#rules', $item['fix']);
    }

    public function test_leadership_needs_a_member(): void
    {
        $data = $this->complete();
        $data['team']['items'][0]['hidden'] = true;
        $data['team']['items'][1]['group'] = 'pedagogue';

        $item = $this->byId($this->evaluate($data), 'rukovodstvo.a');
        $this->assertFalse($item['ok']);
        $this->assertSame('/admin/team/new/', $item['fix']);
    }

    public function test_hidden_staff_are_not_checked_and_first_offender_is_linked(): void
    {
        $data = $this->complete();
        $data['team']['items'][2]['hidden'] = true;
        $data['team']['items'][2]['education'] = '';
        $this->assertTrue($this->byId($this->evaluate($data), 'sostav.a')['ok']);

        $data['team']['items'][2]['hidden'] = false;
        $data['team']['items'][1]['qualification'] = '';
        $data['team']['items'][2]['specialty'] = '';
        $item = $this->byId($this->evaluate($data), 'sostav.a');
        $this->assertFalse($item['ok']);
        $this->assertSame('/admin/team/t00000000002/', $item['fix'], 'первый по порядку на странице');
    }

    public function test_each_venue_in_season_needs_13_access_fields(): void
    {
        $data = $this->complete();
        $data['venues']['items'][0]['access_k'] = '';
        $items = $this->evaluate($data);

        $item = $this->byId($items, 'dostupnaya.k:solnyshko');
        $this->assertFalse($item['ok']);
        $this->assertSame('/admin/venues/v00000000001/#access_k', $item['fix']);
        $this->assertSame(['dostupnaya.k:solnyshko'], $this->failedRequired($items));
        $this->assertStringContainsString('Солнышко', $item['label']);
        $this->assertSame('dostupnaya', $item['section']);

        // п. 11 е — «при наличии»: пустое поле не мешает
        $data['venues']['items'][0]['access_k'] = 'есть';
        $data['venues']['items'][0]['access_e'] = '';
        $this->assertTrue(Readiness::isReady($this->evaluate($data)));
    }

    public function test_mto_items_per_venue_and_health_is_optional(): void
    {
        $data = $this->complete();
        $data['venues']['items'][0]['mto_g'] = '';
        $this->assertTrue(Readiness::isReady($this->evaluate($data)), '9 д — при условии оздоровительной деятельности');

        $data['venues']['items'][0]['mto_b'] = '';
        $data['venues']['items'][0]['mto_d'] = '';
        $items = $this->evaluate($data);
        $this->assertSame(['mto.a:solnyshko', 'mto.g:solnyshko'], $this->failedRequired($items));
        $this->assertSame('/admin/venues/v00000000001/#mto_d', $this->byId($items, 'mto.a:solnyshko')['fix']);
        $this->assertSame('/admin/venues/v00000000001/#mto_b', $this->byId($items, 'mto.g:solnyshko')['fix']);

        $data['venues']['items'][0]['mto_v'] = '';
        $this->assertContains('mto.b:solnyshko', $this->failedRequired($this->evaluate($data)));
        $this->assertContains('mto.v:solnyshko', $this->failedRequired($this->evaluate($data)));
    }

    public function test_only_venues_of_season_shifts_are_checked(): void
    {
        $data = $this->complete();
        $empty = ['id' => 'v00000000002', 'slug' => 'vostok', 'name' => 'ДЗОЛ «Восток»', 'address' => ''];
        $data['venues']['items'][] = $empty;
        $data['shifts']['items'][] = ['id' => 's00000000002', 'title' => 'Вперёд', 'dateStart' => '2027-03-24', 'dateEnd' => '2027-03-31', 'price' => null, 'status' => 'open', 'venue' => 'vostok', 'hidden' => true];
        $data['shifts']['items'][] = ['id' => 's00000000003', 'title' => 'Давняя', 'dateStart' => '2025-07-01', 'dateEnd' => '2025-07-21', 'price' => null, 'status' => 'closed', 'venue' => 'vostok', 'hidden' => false];
        $data['shifts']['items'][] = ['id' => 's00000000004', 'title' => 'Далёкая', 'dateStart' => '2029-07-01', 'dateEnd' => '2029-07-21', 'price' => null, 'status' => 'soon', 'venue' => 'vostok', 'hidden' => false];

        $this->assertSame([], $this->failedRequired($this->evaluate($data)), 'скрытая, прошлая и смена через два года не требуют базу и цену');

        // смена следующего года включается в сезон
        $data['shifts']['items'][3]['dateStart'] = '2028-07-01';
        $data['shifts']['items'][3]['dateEnd'] = '2028-07-21';
        $failed = $this->failedRequired($this->evaluate($data));
        $this->assertContains('mto.a:vostok', $failed);
        $this->assertContains('dostupnaya.a:vostok', $failed);
        $this->assertContains('uslugi.b', $failed, 'у этой смены нет цены');
    }

    public function test_shift_without_venue_is_flagged(): void
    {
        $data = $this->complete();
        $data['shifts']['items'][0]['venue'] = '';
        $items = $this->evaluate($data);

        $item = $this->byId($items, 'mto.venue:spasatel-2027');
        $this->assertFalse($item['ok']);
        $this->assertSame('/admin/shifts/s00000000001/#venue', $item['fix']);
    }

    public function test_no_shifts_in_season_fails_dates_and_checks_venues_of_any_visible_shift(): void
    {
        $data = $this->complete();
        $data['shifts']['items'][0]['dateStart'] = '2029-07-02';
        $data['shifts']['items'][0]['dateEnd'] = '2029-07-22';
        $data['venues']['items'][0]['access_a'] = '';
        $items = $this->evaluate($data);

        $item = $this->byId($items, 'deyatelnost.b');
        $this->assertFalse($item['ok']);
        $this->assertStringContainsString('Нет смен на текущий/следующий год', $item['label']);
        $this->assertSame('/admin/shifts/new/', $item['fix']);
        $this->assertContains('dostupnaya.a:solnyshko', $this->failedRequired($items), 'база смены вне сезона проверяется');

        $data['shifts']['items'] = [];
        $items = $this->evaluate($data);
        $this->assertFalse($this->byId($items, 'deyatelnost.b')['ok']);
        $this->assertSame(['deyatelnost.b', 'uslugi.b'], $this->failedRequired($items));
    }

    public function test_optional_items_never_block(): void
    {
        $data = $this->complete();
        $data['texts']['sved.eduPrograms'] = '';
        $data['texts']['sved.healthPrograms'] = '';
        $data['texts']['sved.methods'] = '';
        $data['texts']['sved.compensation'] = '';
        $data['texts']['sved.shortName'] = '';
        $data['settings']['email'] = '';
        $data['documents']['items'] = array_values(array_filter($data['documents']['items'], static fn (array $d): bool => $d['section'] !== 'programma-razvitiya'));

        $items = $this->evaluate($data);
        $this->assertTrue(Readiness::isReady($items));
        $this->assertSame([], $this->failedRequired($items));
        $ids = array_column($items, 'id');
        foreach (['deyatelnost.v', 'deyatelnost.g', 'deyatelnost.d', 'uslugi.v', 'dokumenty.g', 'kontakty.b', 'mto.d:solnyshko'] as $id) {
            $this->assertNotContains($id, $ids, "$id необязательный: в проверке его нет");
        }
    }

    public function test_prices_required_for_uslugi(): void
    {
        $data = $this->complete();
        $data['shifts']['items'][0]['price'] = null;
        $items = $this->evaluate($data);

        $item = $this->byId($items, 'uslugi.b');
        $this->assertFalse($item['ok']);
        $this->assertSame('/admin/shifts/s00000000001/#price', $item['fix']);
        $this->assertFalse(Readiness::isReady($items));

        $data['shifts']['items'][0]['price'] = 0;
        $this->assertFalse($this->byId($this->evaluate($data), 'uslugi.b')['ok'], 'ноль — не цена');
    }

    public function test_old_document_warning(): void
    {
        $data = $this->complete();
        $data['documents']['items'][2]['date'] = '2025-01-01';
        $items = $this->evaluate($data);

        $warnings = array_values(array_filter($items, static fn (array $i): bool => $i['level'] === 'warning' && !$i['ok']));
        $this->assertCount(1, $warnings);
        $this->assertSame('dokumenty', $warnings[0]['section']);
        $this->assertSame('/admin/documents/d00000000003/', $warnings[0]['fix']);
        $this->assertStringContainsString('Программа воспитательной работы', $warnings[0]['label']);
        $this->assertTrue(Readiness::isReady($items), 'предупреждение не блокирует запуск');

        // уставу сколько угодно лет; ровно 365 дней — ещё не старый
        $data = $this->complete();
        $data['documents']['items'][0]['date'] = '1999-01-01';
        $data['documents']['items'][2]['date'] = '2026-01-15';
        $this->assertSame([], array_filter($this->evaluate($data), static fn (array $i): bool => $i['level'] === 'warning' && !$i['ok']));
    }

    public function test_warning_when_no_open_shifts_and_no_ovz_responsible(): void
    {
        $data = $this->complete();
        $items = $this->evaluate($data);
        $this->assertSame([], array_filter($items, static fn (array $i): bool => $i['level'] === 'warning' && !$i['ok']), 'в полной выборке предупреждений нет');

        $data['shifts']['items'][0]['status'] = 'closed';
        $data['team']['items'][1]['ovzResponsible'] = false;
        $items = $this->evaluate($data);
        $warn = array_column(array_filter($items, static fn (array $i): bool => $i['level'] === 'warning' && !$i['ok']), null, 'id');

        $this->assertSame(['warn.openShifts', 'warn.ovz'], array_keys($warn));
        $this->assertSame('/admin/shifts/', $warn['warn.openShifts']['fix']);
        $this->assertSame('/admin/team/', $warn['warn.ovz']['fix']);
        $this->assertTrue(Readiness::isReady($items));
    }

    public function test_is_ready_ignores_warnings_and_needs_every_required(): void
    {
        $this->assertTrue(Readiness::isReady([]));
        $this->assertTrue(Readiness::isReady([['id' => 'a', 'section' => 's', 'label' => '', 'ok' => false, 'level' => 'warning', 'fix' => '/']]));
        $this->assertFalse(Readiness::isReady([['id' => 'a', 'section' => 's', 'label' => '', 'ok' => false, 'level' => 'required', 'fix' => '/']]));
    }

    public function test_tolerates_garbage_data(): void
    {
        $items = Readiness::evaluate([
            'settings' => 'x', 'texts' => ['sved.addresses' => 'не список', 'sved.fullName' => ['a']],
            'shifts' => [['venue' => 5, 'dateStart' => 'вчера'], 'мусор'], 'venues' => ['items' => 'нет'],
            'team' => [['name' => ['x']]], 'documents' => ['items' => [['section' => 'inoe', 'file' => 'f']]],
        ], $this->now());

        $this->assertFalse(Readiness::isReady($items));
    }
}
