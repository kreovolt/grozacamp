<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Pages;

use Groza\App;
use Groza\Pages\SvedeniyaPage;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Groza\View\t;

/** Раздел сведений (спека §7.6, Приказ № 201). Данные — seed/demo, часы — 2027-01-15. */
final class SvedeniyaPageTest extends TestCase
{
    private const PAGES = [
        '/svedeniya/ob-organizacii/', '/svedeniya/deyatelnost/', '/svedeniya/mto/', '/svedeniya/uslugi/',
        '/svedeniya/dostupnaya-sreda/', '/svedeniya/inaya-informaciya/',
    ];

    /** @param array<string,mixed> $patch */
    private function withTexts(array $patch): App
    {
        $app = TestApp::make();
        $app->store()->write('texts', array_replace($app->store()->read('texts'), $patch), 'test');

        return $app;
    }

    /** @param callable(array<string,mixed>):array<string,mixed> $change */
    private function withItems(App $app, string $name, callable $change): void
    {
        $data = $app->store()->read($name);
        $data['items'] = $change($data['items']);
        $app->store()->write($name, $data, 'test');
    }

    /** Текст без тегов, неразрывные пробелы — обычными. */
    private function plain(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $text));
    }

    /** Строка пункта: <div data-item="…"> … </div> (внутри строки вложенных div нет). */
    private function row(string $html, string $id): string
    {
        $this->assertSame(1, preg_match('#<div[^>]*\sdata-item="[^"]*\b' . preg_quote($id, '#') . '\b[^"]*"[^>]*>(.*?)</div>#us', $html, $m), "нет строки $id");

        return $m[1];
    }

    private function section(string $html, string $id): string
    {
        $this->assertSame(1, preg_match('#<section[^>]*\sid="' . preg_quote($id, '#') . '"[^>]*>(.*?)</section>#us', $html, $m), "нет секции #$id");

        return $m[1];
    }

    /** @return array<string,list<string>> */
    public static function titles(): array
    {
        return [
            'hub' => ['/svedeniya/', 'Сведения об организации отдыха детей и их оздоровления'],
            'ob' => ['/svedeniya/ob-organizacii/', 'Об организации отдыха детей и их оздоровления'],
            'deyatelnost' => ['/svedeniya/deyatelnost/', 'Деятельность'],
            'mto' => ['/svedeniya/mto/', 'Материально-техническое обеспечение и оснащенность организации отдыха детей и их оздоровления'],
            'uslugi' => ['/svedeniya/uslugi/', 'Услуги, в том числе платные, предоставляемые организацией отдыха детей и их оздоровления'],
            'dostupnaya' => ['/svedeniya/dostupnaya-sreda/', 'Доступная среда'],
            'inaya' => ['/svedeniya/inaya-informaciya/', 'Иная информация'],
        ];
    }

    #[DataProvider('titles')]
    public function test_titles_exact(string $path, string $h1): void
    {
        $res = TestApp::get(TestApp::make(), $path);

        $this->assertSame(200, $res->status());
        $this->assertSame(1, preg_match_all('#<h1[^>]*>\s*(.*?)\s*</h1>#us', $res->body(), $m));
        $this->assertSame(t($h1), $m[1][0], 'формулировка приказа дословно (пробелы — через типограф)');
        $this->assertSame($h1, $this->plain($m[1][0]));
        $this->assertStringContainsString('<title>' . $h1, html_entity_decode($res->body(), ENT_QUOTES | ENT_HTML5));
    }

    #[DataProvider('titles')]
    public function test_breadcrumbs_lead_back_to_hub(string $path, string $h1): void
    {
        $body = TestApp::get(TestApp::make(), $path)->body();
        $this->assertSame(1, preg_match('#aria-label="Хлебные крошки"(.*?)</nav>#us', $body, $m));

        $this->assertStringContainsString('href="/"', $m[1]);
        $this->assertSame($path !== '/svedeniya/', str_contains($m[1], 'href="/svedeniya/"'));
        $this->assertSame(1, preg_match_all('#aria-current="page"#', $m[1]));
    }

    public function test_hub_links_all_sections_and_anchors(): void
    {
        $body = TestApp::get(TestApp::make(), '/svedeniya/')->body();
        $this->assertSame(1, preg_match('#<nav[^>]*aria-label="Разделы сведений"[^>]*>(.*?)</nav>#us', $body, $m));
        $list = $m[1];

        $this->assertSame(6, preg_match_all('#href="/svedeniya/[a-z-]+/"#', $list));
        $this->assertSame(5, preg_match_all('#href="/svedeniya/ob-organizacii/\#[a-z-]+"#', $list));
        foreach (['osnovnye-svedeniya', 'dokumenty', 'rukovodstvo', 'sostav', 'kontakty'] as $anchor) {
            $this->assertStringContainsString('href="/svedeniya/ob-organizacii/#' . $anchor . '"', $list);
        }
        $this->assertStringContainsString('приказом Минпросвещения России от 14.03.2025 № 201', $this->plain($body), 'основание — приказ № 201');
    }

    public function test_ob_organizacii_has_five_anchored_parts(): void
    {
        $body = TestApp::get(TestApp::make(), '/svedeniya/ob-organizacii/')->body();

        preg_match_all('#<section[^>]*\sid="(osnovnye-svedeniya|dokumenty|rukovodstvo|sostav|kontakty)"#u', $body, $m);
        $this->assertSame(['osnovnye-svedeniya', 'dokumenty', 'rukovodstvo', 'sostav', 'kontakty'], $m[1]);
        foreach ($m[1] as $id) {
            $this->assertStringContainsString('href="#' . $id . '"', $body, "оглавление: #$id");
        }
        foreach (['Основные сведения', 'Документы', 'Руководство', 'Педагогический и вожатский состав', 'Контакты'] as $i => $h2) {
            $this->assertStringContainsString('>' . t($h2) . '<', $this->section($body, $m[1][$i]));
        }
    }

    public function test_osnovnye_rows_carry_the_legal_wording(): void
    {
        $body = TestApp::get(TestApp::make(), '/svedeniya/ob-organizacii/')->body();
        $part = $this->section($body, 'osnovnye-svedeniya');

        preg_match_all('#\sdata-item="([a-z.]+)"#', $part, $m);
        $this->assertSame(['osnovnye.a', 'osnovnye.b', 'osnovnye.v', 'osnovnye.g', 'osnovnye.d', 'osnovnye.e'], $m[1]);
        $this->assertStringContainsString('фамилия, имя, отчество (при наличии) руководителя Организации отдыха либо индивидуального предпринимателя', $this->plain($this->row($part, 'osnovnye.b')));
        $this->assertStringContainsString('Лучников Евгений Алексеевич', $this->row($part, 'osnovnye.b'));
        $this->assertStringContainsString('ПРОО ВПЦ «Гроза»', $this->plain($this->row($part, 'osnovnye.a')), 'краткое наименование рядом с полным');
        $this->assertStringContainsString('3 б', $this->plain($this->row($part, 'osnovnye.b')), 'номер пункта приказа');
    }

    public function test_addresses_fall_back_to_office_and_season_bases(): void
    {
        $app = $this->withTexts(['sved.addresses' => []]);
        $part = $this->section(TestApp::get($app, '/svedeniya/ob-organizacii/')->body(), 'osnovnye-svedeniya');
        $row = $this->plain($this->row($part, 'osnovnye.d'));

        $this->assertStringContainsString('ул. Милиционера Власова, 37', $row, 'адрес офиса');
        $this->assertStringContainsString('ДЗОЛ «Солнышко»', $row, 'база сезона');
        $this->assertStringContainsString('дер. Коляды', $row);
        $this->assertStringNotContainsString('Восток', $row, 'база без смен сезона не называется');
    }

    public function test_staff_table_columns(): void
    {
        $part = $this->section(TestApp::get(TestApp::make(), '/svedeniya/ob-organizacii/')->body(), 'sostav');

        preg_match_all('#<th[^>]*scope="col"[^>]*>(.*?)</th>#us', $part, $m);
        $this->assertSame(['ФИО', 'Должность', 'Образование', 'Направление подготовки / специальность', 'Квалификация'], array_map($this->plain(...), $m[1]));
        $this->assertSame(17, substr_count($part, '<tr data-staff'), '8 педагогов и 9 вожатых');
        $this->assertStringNotContainsString('Каменских', $part, 'группа «другое» не педагогический состав');
        $this->assertStringNotContainsString('Лучников', $part, 'руководство — отдельный раздел (п. 5)');
        $this->assertStringContainsString('Информация обновляется', $part, 'у части сотрудников образование не заполнено');
        $this->assertStringContainsString('Высшее (пример)', $part);
    }

    public function test_staff_table_hides_hidden_members_and_lists_ovz_responsible(): void
    {
        $app = TestApp::make();
        $this->withItems($app, 'team', static function (array $items): array {
            $items[2]['hidden'] = true; // Манохин
            $items[3]['ovzResponsible'] = true; // Новикова

            return $items;
        });
        $part = $this->section(TestApp::get($app, '/svedeniya/ob-organizacii/')->body(), 'sostav');

        $this->assertStringNotContainsString('Манохин', $part);
        $this->assertSame(16 + 1, substr_count($part, '<tr data-staff'), '16 в составе и один ответственный за ОВЗ');
        $this->assertStringContainsString('ответственных за организацию работы по обеспечению доступности', $this->plain($this->row($part, 'sostav.b')));
    }

    public function test_no_ovz_responsible_row_is_omitted(): void
    {
        $part = $this->section(TestApp::get(TestApp::make(), '/svedeniya/ob-organizacii/')->body(), 'sostav');

        $this->assertStringNotContainsString('data-item="sostav.b"', $part);
        $this->assertStringNotContainsString('Не предусмотрено', $part);
    }

    public function test_rukovodstvo_lists_leadership(): void
    {
        $part = $this->section(TestApp::get(TestApp::make(), '/svedeniya/ob-organizacii/')->body(), 'rukovodstvo');

        $this->assertStringContainsString('Лучников Евгений Алексеевич', $part);
        $this->assertStringContainsString('Заместитель директора', $part);
        $this->assertStringNotContainsString('Манохин', $part);
    }

    public function test_empty_required_shows_pending(): void
    {
        $app = $this->withTexts(['sved.legalForm' => '', 'sved.mode' => '<p></p>']);
        $part = $this->section(TestApp::get($app, '/svedeniya/ob-organizacii/')->body(), 'osnovnye-svedeniya');

        $this->assertStringContainsString('Информация обновляется', $this->row($part, 'osnovnye.v'));
        $this->assertStringContainsString('Информация обновляется', $this->row($part, 'osnovnye.e'));
        $this->assertStringNotContainsString('Информация обновляется', $this->row($part, 'osnovnye.b'));
    }

    public function test_empty_optional_rows_are_omitted(): void
    {
        $app = $this->withTexts(['sved.eduPrograms' => '', 'sved.healthPrograms' => '', 'sved.methods' => '', 'sved.compensation' => '']);
        $dey = TestApp::get($app, '/svedeniya/deyatelnost/')->body();
        foreach (['deyatelnost.v', 'deyatelnost.g', 'deyatelnost.d'] as $id) {
            $this->assertStringNotContainsString('data-item="' . $id . '"', $dey, $id);
        }
        $this->assertStringNotContainsString('id="programmy"', $dey, 'раздел без пунктов не выводится');
        $this->assertStringNotContainsString('href="#programmy"', $dey);
        $uslugi = TestApp::get($app, '/svedeniya/uslugi/')->body();
        $this->assertStringNotContainsString('data-item="uslugi.v"', $uslugi);
        $this->assertStringNotContainsString('id="kompensaciya"', $uslugi);
        foreach ([$dey, $uslugi] as $body) {
            $this->assertStringNotContainsString('Не предусмотрено', $body);
        }

        $filled = TestApp::get(TestApp::make(), '/svedeniya/uslugi/')->body();
        $this->assertStringContainsString('сертификаты', mb_strtolower($this->plain($this->row($filled, 'uslugi.v'))));
    }

    public function test_documents_show_type_and_size(): void
    {
        $app = TestApp::make();
        $this->withItems($app, 'documents', static function (array $items): array {
            $items[0]['file']['bytes'] = 1258291;
            $items[1]['file']['bytes'] = 512000;

            return $items;
        });
        $part = $this->section(TestApp::get($app, '/svedeniya/ob-organizacii/')->body(), 'dokumenty');
        $row = $this->plain($this->row($part, 'dokumenty.v'));

        $this->assertStringContainsString('Программа воспитательной работы (пример)', $row);
        $this->assertStringContainsString('PDF, 1,2 МБ', $row);
        $this->assertStringContainsString('PDF, 500 КБ', $row);
        $this->assertStringContainsString('1 мая 2026', $row, 'дата документа');
        $this->assertStringContainsString('href="/uploads/docs/demo-programma-vospitaniya.pdf"', $part);
    }

    public function test_documents_missing_are_pending_and_optional_is_omitted(): void
    {
        $part = $this->section(TestApp::get(TestApp::make(), '/svedeniya/ob-organizacii/')->body(), 'dokumenty');

        $this->assertStringContainsString('Информация обновляется', $this->row($part, 'dokumenty.a'), 'устава в демо нет');
        $rules = $this->row($part, 'dokumenty.b');
        $this->assertStringContainsString('Информация обновляется', $rules, 'скана правил нет, текст страницы его не заменяет');
        $this->assertStringContainsString('href="/pravila/"', $rules, 'но ссылка на страницу правил есть');
        $this->assertStringNotContainsString('Информация обновляется', $this->row($part, 'dokumenty.v'));
        $this->assertStringNotContainsString('data-item="dokumenty.g"', $part, 'программы развития нет — пункт не выводится');
    }

    public function test_kontakty(): void
    {
        $part = $this->section(TestApp::get(TestApp::make(), '/svedeniya/ob-organizacii/')->body(), 'kontakty');

        $this->assertStringContainsString('href="tel:+73422474347"', $this->row($part, 'kontakty.a'));
        $this->assertStringContainsString('+7 (342) 247-43-47', $this->plain($this->row($part, 'kontakty.a')));
        $this->assertStringContainsString('href="mailto:2474347@bk.ru"', $this->row($part, 'kontakty.b'));
        $this->assertStringContainsString("614065, г.\u{00A0}Пермь", $this->row($part, 'kontakty.v'));

        $app = TestApp::make();
        $app->store()->write('settings', ['postalAddress' => ''] + $app->store()->read('settings'), 'test');
        $this->assertStringContainsString('Информация обновляется', $this->row($this->section(TestApp::get($app, '/svedeniya/ob-organizacii/')->body(), 'kontakty'), 'kontakty.v'));
    }

    public function test_deyatelnost_table_comes_from_shifts(): void
    {
        $body = TestApp::get(TestApp::make(), '/svedeniya/deyatelnost/')->body();
        $row = $this->plain($this->row($body, 'deyatelnost.b'));

        $this->assertStringContainsString('2–22 июля 2027', $row);
        $this->assertStringContainsString('Спасатель', $row);
        $this->assertStringContainsString('Штурмовик', $row);
        $this->assertStringContainsString('Пограничник', $row);
        $this->assertStringNotContainsString('Вперёд и вверх', $row, 'скрытая смена');
        $this->assertStringContainsString('ДЗОЛ «Солнышко»', $row);
        $this->assertStringContainsString('8–17 лет', $row);
    }

    public function test_deyatelnost_without_shifts_is_pending(): void
    {
        $app = TestApp::make();
        $this->withItems($app, 'shifts', static fn (array $items): array => []);

        $this->assertStringContainsString('Информация обновляется', $this->row(TestApp::get($app, '/svedeniya/deyatelnost/')->body(), 'deyatelnost.b'));
    }

    public function test_mto_per_season_venue(): void
    {
        $body = TestApp::get(TestApp::make(), '/svedeniya/mto/')->body();
        $venue = $this->section($body, 'solnyshko');

        $this->assertStringNotContainsString('ДЗОЛ «Восток»', $body, 'база без смен сезона не показывается');
        $this->assertStringContainsString('>ДЗОЛ «Солнышко»<', $venue);
        $this->assertStringContainsString("дер.\u{00A0}Коляды", $venue);
        preg_match_all('#\sdata-item="([a-z. ]+)"#', $venue, $m);
        $this->assertSame(['mto.a', 'mto.b', 'mto.v', 'mto.g', 'mto.d'], $m[1], 'по одному полю на пункт 9 а–д');
        $this->assertStringContainsString('1985', $this->row($venue, 'mto.a'));
        $this->assertStringContainsString(t('Деревянные корпуса с санузлом'), $this->row($venue, 'mto.b'));
        $this->assertStringContainsString(t('питание пять раз в день'), $this->row($venue, 'mto.v'));
        $this->assertStringContainsString(t('Корпуса для занятий'), $this->row($venue, 'mto.g'));
        $this->assertStringContainsString('библиотеки нет', $this->row($venue, 'mto.g'));
        $this->assertStringContainsString('Медицинский пункт', $this->row($venue, 'mto.d'));
    }

    public function test_dostupnaya_lists_13_items_per_venue(): void
    {
        $body = TestApp::get(TestApp::make(), '/svedeniya/dostupnaya-sreda/')->body();
        $venue = $this->section($body, 'solnyshko');

        preg_match_all('#\sdata-item="(dostupnaya\.[a-z]+)"#', $venue, $m);
        $this->assertSame(array_map(static fn (string $l): string => 'dostupnaya.' . $l, ['a', 'b', 'v', 'g', 'd', 'e', 'zh', 'z', 'i', 'k', 'l', 'm', 'n']), $m[1]);
        preg_match_all('#<span class="sved-par[^"]*">п\. 11 ([а-я])</span>#u', $venue, $p);
        $this->assertSame(['а', 'б', 'в', 'г', 'д', 'е', 'ж', 'з', 'и', 'к', 'л', 'м', 'н'], $p[1], 'буквы по порядку');
        $this->assertStringContainsString('о допуске и условиях перемещения', $this->plain($this->row($venue, 'dostupnaya.n')));
        $this->assertStringNotContainsString('Восток', $body);
    }

    public function test_dostupnaya_empty_required_pending_optional_omitted(): void
    {
        $app = TestApp::make();
        $this->withItems($app, 'venues', static function (array $items): array {
            $items[0]['access_k'] = '';
            $items[0]['access_e'] = '';
            $items[0]['mto_d'] = '';
            $items[0]['mto_v'] = '';

            return $items;
        });
        $venue = $this->section(TestApp::get($app, '/svedeniya/dostupnaya-sreda/')->body(), 'solnyshko');

        $this->assertStringContainsString('Информация обновляется', $this->row($venue, 'dostupnaya.k'));
        $this->assertStringNotContainsString('data-item="dostupnaya.e"', $venue, 'п. 11 е — при наличии');
        $this->assertSame(12, substr_count($venue, 'data-item="dostupnaya.'));
        $this->assertStringNotContainsString('Информация обновляется', $this->row($venue, 'dostupnaya.l'));

        $mto = TestApp::get($app, '/svedeniya/mto/')->body();
        $this->assertStringContainsString('Информация обновляется', $this->row($mto, 'mto.v'), 'питание не описано — это видно отдельно от проживания');
        $this->assertStringNotContainsString('Информация обновляется', $this->row($mto, 'mto.b'));
        $this->assertStringNotContainsString('data-item="mto.d"', $mto);
    }

    public function test_two_venues_get_a_toc(): void
    {
        $app = TestApp::make();
        $this->withItems($app, 'shifts', static function (array $items): array {
            $items[3]['hidden'] = false; // «Вперёд и вверх» на «Востоке», без цены

            return $items;
        });
        $body = TestApp::get($app, '/svedeniya/dostupnaya-sreda/')->body();

        $this->assertStringContainsString('href="#solnyshko"', $body);
        $this->assertStringContainsString('href="#vostok"', $body);
        $this->assertStringContainsString('Информация обновляется', $this->row($this->section($body, 'vostok'), 'dostupnaya.a'));
    }

    public function test_uslugi_shows_cost_per_day(): void
    {
        $body = TestApp::get(TestApp::make(), '/svedeniya/uslugi/')->body();
        $row = $this->plain($this->row($body, 'uslugi.b'));

        $this->assertStringContainsString('≈ 2 857 ₽ в день', $row);
        $this->assertStringContainsString('60 000 ₽', $row);
        $this->assertStringContainsString('Пограничник', $row);
        $this->assertStringNotContainsString('Вперёд и вверх', $row);
        $this->assertStringContainsString("≈\u{00A0}2\u{00A0}857\u{00A0}₽", $body, 'разряды неразрывными пробелами');
        $this->assertMatchesRegularExpression('#среднем[^<]*≈#u', $this->row($body, 'uslugi.b'), 'средняя стоимость дня по сменам');
    }

    public function test_uslugi_price_comes_from_shifts_not_from_text(): void
    {
        $app = TestApp::make();
        $this->withItems($app, 'shifts', static function (array $items): array {
            $items[0]['price'] = 63000;
            $items[1]['price'] = null;

            return $items;
        });
        $row = $this->plain($this->row(TestApp::get($app, '/svedeniya/uslugi/')->body(), 'uslugi.b'));

        $this->assertStringContainsString('63 000 ₽', $row);
        $this->assertStringContainsString('≈ 3 000 ₽ в день', $row);
        $this->assertStringContainsString('Информация обновляется', $row, 'у смены без цены');
    }

    public function test_uslugi_lists_documents_and_packing_from_shared_texts(): void
    {
        $app = TestApp::make();
        $texts = $app->store()->read('texts');
        $body = TestApp::get($app, '/svedeniya/uslugi/')->body();

        foreach ($texts['lists.documents'] as $item) {
            $this->assertStringContainsString(t($item), $this->row($body, 'uslugi.g'));
        }
        foreach ($texts['lists.packing'] as $item) {
            $this->assertStringContainsString(t($item), $this->row($body, 'uslugi.d'));
        }
        $this->assertStringContainsString('Информация обновляется', $this->row(TestApp::get($this->withTexts(['lists.packing' => []]), '/svedeniya/uslugi/')->body(), 'uslugi.d'));
    }

    public function test_year_shifts_stay_on_the_pages_after_they_end(): void
    {
        $app = TestApp::make(['fakeNow' => '2027-12-20T12:00:00+05:00']);
        $dey = $this->plain($this->row(TestApp::get($app, '/svedeniya/deyatelnost/')->body(), 'deyatelnost.b'));
        $this->assertStringContainsString('Спасатель', $dey);
        $this->assertStringContainsString('2–22 июля 2027', $dey);
        $this->assertStringNotContainsString('Информация обновляется', $dey);

        $uslugi = $this->plain($this->row(TestApp::get($app, '/svedeniya/uslugi/')->body(), 'uslugi.b'));
        $this->assertStringContainsString('60 000 ₽', $uslugi);
        $this->assertStringContainsString('≈ 2 857 ₽ в день', $uslugi);

        $this->assertStringContainsString('>ДЗОЛ «Солнышко»<', TestApp::get($app, '/svedeniya/mto/')->body());
    }

    public function test_inaya_informaciya(): void
    {
        $body = TestApp::get(TestApp::make(), '/svedeniya/inaya-informaciya/')->body();

        $this->assertStringContainsString(t('Номер в реестре организаций отдыха'), $body);
        $this->assertStringContainsString('href="/policy/"', $body);
        $this->assertStringContainsString('href="/pravila/"', $body);
    }

    public function test_updated_date_shown(): void
    {
        $app = TestApp::make();
        $dir = $app->config()['storageDir'] . '/data/';
        foreach (['texts', 'settings', 'documents', 'team', 'venues', 'shifts'] as $name) {
            touch($dir . $name . '.json', strtotime('2026-03-01 12:00:00 UTC'));
        }
        touch($dir . 'team.json', strtotime('2026-11-20 08:00:00 UTC'));

        $body = TestApp::get($app, '/svedeniya/ob-organizacii/')->body();
        $this->assertStringContainsString('Обновлено: 20 ноября 2026', $this->plain($body));
        $this->assertMatchesRegularExpression('#<time datetime="2026-11-20">#', $body);

        // страница, которая команду не читает, на её правку не реагирует
        $this->assertStringContainsString('Обновлено: 1 марта 2026', $this->plain(TestApp::get($app, '/svedeniya/mto/')->body()));
    }

    public function test_user_text_is_escaped_and_richtext_is_kept(): void
    {
        $app = $this->withTexts(['sved.fullName' => 'ООО <script>alert(1)</script> «Гроза»', 'sved.mode' => '<p>Сезонный <strong>режим</strong></p>']);
        $this->withItems($app, 'venues', static function (array $items): array {
            $items[0]['mto_a'] = '<img src=x onerror=alert(1)>';
            $items[0]['name'] = 'База <b>1</b>';

            return $items;
        });
        $ob = TestApp::get($app, '/svedeniya/ob-organizacii/')->body();
        $mto = TestApp::get($app, '/svedeniya/mto/')->body();

        $this->assertStringNotContainsString('<script>alert(1)', $ob);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $ob);
        $this->assertStringContainsString('<p>Сезонный <strong>режим</strong></p>', $ob);
        $this->assertStringNotContainsString('<img src=x', $mto);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $mto);
        $this->assertStringNotContainsString('База <b>1</b>', $mto);
    }

    public function test_side_toc_and_updated_exist_on_documents(): void
    {
        $body = TestApp::get(TestApp::make(), '/svedeniya/uslugi/')->body();

        $this->assertSame(1, preg_match('#<nav[^>]*aria-label="Содержание"#u', $body), 'боковое оглавление');
        $this->assertStringContainsString('Обновлено:', $this->plain($body));
    }

    public function test_pages_survive_empty_data(): void
    {
        $app = TestApp::make();
        foreach (['shifts', 'venues', 'team', 'documents'] as $name) {
            $app->store()->write($name, ['items' => []], 'test');
        }
        $app->store()->write('texts', [], 'test');
        $app->store()->write('settings', ['phone' => '', 'email' => '', 'postalAddress' => '', 'officeAddress' => ''], 'test');

        foreach (['/svedeniya/', ...self::PAGES] as $path) {
            $res = TestApp::get($app, $path);
            $this->assertSame(200, $res->status(), $path);
        }
        $this->assertStringContainsString('Информация обновляется', TestApp::get($app, '/svedeniya/mto/')->body());
    }

    public function test_file_size_switches_to_megabytes_without_a_1024_kb_edge(): void
    {
        $nb = "\u{00A0}";
        $this->assertSame('1' . $nb . 'КБ', SvedeniyaPage::fileSize(1));
        $this->assertSame('500' . $nb . 'КБ', SvedeniyaPage::fileSize(512000));
        $this->assertSame('1023' . $nb . 'КБ', SvedeniyaPage::fileSize(1047552));
        $this->assertSame('1,0' . $nb . 'МБ', SvedeniyaPage::fileSize(1048200), '1048200 Б округляется до 1024 КБ — это уже мегабайт');
        $this->assertSame('1,2' . $nb . 'МБ', SvedeniyaPage::fileSize(1258291));
    }

    /** Старые заглушки меню заменены настоящими страницами. */
    public function test_placeholders_are_gone(): void
    {
        foreach (['/svedeniya/', ...self::PAGES] as $path) {
            $this->assertStringNotContainsString('Раздел наполняется', TestApp::get(TestApp::make(), $path)->body(), $path);
        }
    }
}
