<?php
declare(strict_types=1);

namespace Groza\Readiness;

use DateTimeImmutable;
use Groza\App;
use Groza\Content\PageData;
use Groza\Content\Staff;
use Groza\Support\Dates;

/**
 * «Готовность к проверке»: что из пунктов 3–11 приказа Минпросвещения № 201 на сайте не заполнено.
 * Пункты — из config/order201.php; необязательные («при наличии») в проверку не попадают, они ничему не мешают.
 *
 * Объём проверки по базам — «сезон»: базы, где идут видимые смены с началом в текущем или следующем году (по часам приложения).
 * Если таких смен нет, проверяются базы всех нескрытых смен; если нет и их — не хватает самих смен (п. 8 б).
 */
final class Readiness
{
    /** Документы, которые устаревают: старше года — предупреждение. Уставу старость не мешает. */
    private const AGING_DOCUMENTS = ['pravila', 'programma-vospitaniya', 'kalendarnyj-plan', 'programma-razvitiya'];
    private const OLD_AFTER_DAYS = 365;
    private const DATE = '/^\d{4}-\d{2}-\d{2}$/';

    /** @var list<array<string,mixed>>|null */
    private static ?array $order = null;
    /** @var array<string,array<string,mixed>>|null */
    private static ?array $registry = null;

    /** Данные для проверки из хранилища приложения. @return array<string,mixed> */
    public static function dataFrom(App $app): array
    {
        $store = $app->store();

        return [
            'settings' => $app->settings(),
            'texts' => $store->read('texts'),
            'shifts' => $store->read('shifts'),
            'venues' => $store->read('venues'),
            'team' => $store->read('team'),
            'documents' => $store->read('documents'),
        ];
    }

    /**
     * @param array<string,mixed> $data settings и texts — как в хранилище; shifts, venues, team, documents — коллекция `{items}` или список записей
     * @return list<array{id:string, section:string, label:string, ok:bool, level:'required'|'warning', fix:string}>
     */
    public static function evaluate(array $data, DateTimeImmutable $now): array
    {
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $texts = is_array($data['texts'] ?? null) ? $data['texts'] : [];
        $shifts = self::rows($data['shifts'] ?? []);
        $venues = self::rows($data['venues'] ?? []);
        $team = self::rows($data['team'] ?? []);
        $documents = self::rows($data['documents'] ?? []);
        $scope = self::scope($shifts, $venues, $now);

        $out = [];
        $add = static function (string $id, string $label, bool $ok, string $fix, string $level = 'required', ?string $section = null) use (&$out): void {
            $out[] = ['id' => $id, 'section' => $section ?? explode('.', $id)[0], 'label' => $label, 'ok' => $ok, 'level' => $level, 'fix' => $fix];
        };
        $text = static fn (string $key): bool => self::filled($texts[$key] ?? null);
        $textFix = static fn (string $key): string => '/admin/texts/' . (self::registry()[$key]['group'] ?? 'svedeniya') . '/#' . $key;
        $setting = static fn (string $key): bool => self::filled($settings[$key] ?? null);

        $leaders = Staff::pick($team, ['leadership']);
        $pedagogical = Staff::pick($team, Staff::PEDAGOGICAL);
        $docsOf = static fn (string $section): array => array_values(array_filter(
            $documents,
            static fn (array $d): bool => ($d['section'] ?? null) === $section && PageData::docFile($d) !== null
        ));

        foreach (self::order() as $def) {
            if ($def['level'] !== 'required') {
                // «при наличии»: не мешает запуску, но пустое поле — предупреждение (заполнить или подтвердить, что не применяется)
                if ($def['id'] !== 'sostav.b') { // про ОВЗ — отдельное предупреждение warn.ovz
                    self::optionalWarnings($def, $add, $texts, $settings, $scope['venues'], $docsOf);
                }
                continue;
            }
            $id = $def['id'];
            $title = $def['title'];

            switch ($id) {
                case 'osnovnye.a': case 'osnovnye.b': case 'osnovnye.v': case 'osnovnye.g': case 'osnovnye.e':
                case 'deyatelnost.a': case 'uslugi.a': case 'uslugi.g': case 'uslugi.d':
                    $key = substr($def['source'], strlen('texts:'));
                    $add($id, $title, $text($key), $textFix($key));
                    break;

                case 'osnovnye.d': // офис берётся из настроек, поэтому достаточно либо списка адресов, либо адреса офиса
                    $add($id, $title, $text('sved.addresses') || $setting('officeAddress'), $textFix('sved.addresses'));
                    break;

                case 'dokumenty.a':
                    $add($id, $title, $docsOf('uchreditelnye') !== [], '/admin/documents/new/?section=uchreditelnye');
                    break;

                case 'dokumenty.b': // приказ просит скан-копию: текст страницы /pravila/ её не заменяет
                    $add($id, $docsOf('pravila') === [] ? $title . ': загрузите скан правил (текст на странице /pravila/ скан не заменяет)' : $title, $docsOf('pravila') !== [], '/admin/documents/new/?section=pravila');
                    break;

                case 'dokumenty.v':
                    $missing = array_values(array_filter(['programma-vospitaniya', 'kalendarnyj-plan'], static fn (string $s): bool => $docsOf($s) === []));
                    $add($id, $title, $missing === [], '/admin/documents/new/?section=' . ($missing[0] ?? 'programma-vospitaniya'));
                    break;

                case 'rukovodstvo.a':
                    $named = array_filter($leaders, static fn (array $m): bool => self::str($m['role'] ?? null) !== '');
                    $add($id, $title, $named !== [], '/admin/team/new/');
                    break;

                case 'sostav.a':
                    $bad = array_values(array_filter($pedagogical, static fn (array $m): bool => self::str($m['role'] ?? null) === '' || self::str($m['education'] ?? null) === ''));
                    $first = $bad[0]['id'] ?? null;
                    $add(
                        $id,
                        $bad === [] ? $title : sprintf('%s: не заполнены должность или уровень образования у %d из %d', $title, count($bad), count($pedagogical)),
                        $pedagogical !== [] && $bad === [],
                        is_string($first) && $first !== '' ? "/admin/team/$first/" : '/admin/team/'
                    );
                    break;

                case 'kontakty.a': case 'kontakty.v':
                    $key = substr($def['source'], strlen('settings:'));
                    $add($id, $title, $setting($key), '/admin/settings/#' . $key);
                    break;

                case 'deyatelnost.b':
                    $add($id, $scope['season'] === [] ? 'Нет смен на текущий/следующий год' : $title, $scope['season'] !== [], '/admin/shifts/new/');
                    break;

                case 'uslugi.b':
                    $unpriced = array_values(array_filter($scope['season'], static fn (array $s): bool => PageData::price($s['price'] ?? null) === null));
                    $first = $unpriced[0] ?? null;
                    $add(
                        $id,
                        $scope['season'] === [] ? $title . ': нет смен с ценой' : ($unpriced === [] ? $title : sprintf('%s: у смены «%s» нет цены', $title, self::str($first['title'] ?? null))),
                        $scope['season'] !== [] && $unpriced === [],
                        $first === null ? '/admin/shifts/new/' : '/admin/shifts/' . self::str($first['id'] ?? null) . '/#price'
                    );
                    break;

                default:
                    if (str_starts_with($id, 'mto.') || str_starts_with($id, 'dostupnaya.')) {
                        if ($id === 'mto.a') { // смена без базы: проверять нечего, это и есть недочёт
                            foreach ($scope['unlinked'] as $shift) {
                                $slug = self::str($shift['slug'] ?? null) ?: self::str($shift['id'] ?? null);
                                $add(
                                    'mto.venue:' . $slug,
                                    sprintf('У смены «%s» не выбрана база', self::str($shift['title'] ?? null)),
                                    false,
                                    '/admin/shifts/' . self::str($shift['id'] ?? null) . '/#venue'
                                );
                            }
                        }
                        foreach ($scope['venues'] as $venue) {
                            $fields = explode(',', substr($def['source'], strlen('venues:')));
                            $empty = array_values(array_filter($fields, static fn (string $f): bool => !self::filled($venue[$f] ?? null)));
                            $add(
                                $id . ':' . self::str($venue['slug'] ?? null),
                                $title . ': ' . self::str($venue['name'] ?? null),
                                $empty === [],
                                '/admin/venues/' . self::str($venue['id'] ?? null) . '/#' . ($empty[0] ?? $fields[0])
                            );
                        }
                    }
            }
        }

        // Предупреждения: запуск не блокируют
        $today = $now->format('Y-m-d');
        $open = array_filter($scope['season'], static fn (array $s): bool => (string) $s['dateEnd'] >= $today && in_array($s['status'] ?? null, ['open', 'few'], true));
        $add('warn.openShifts', 'Нет предстоящих смен с открытым набором в текущем и следующем году', $open !== [], '/admin/shifts/', 'warning', 'deyatelnost');
        $ovz = array_filter(Staff::all($team), static fn (array $m): bool => !empty($m['ovzResponsible']));
        $add('warn.ovz', 'Не указан сотрудник, ответственный за работу с детьми с ОВЗ и детьми-инвалидами', $ovz !== [], '/admin/team/', 'warning', 'sostav');
        $thin = array_values(array_filter($pedagogical, static fn (array $m): bool => self::str($m['specialty'] ?? null) === '' || self::str($m['qualification'] ?? null) === ''));
        if ($thin !== []) {
            $first = $thin[0]['id'] ?? null;
            $add(
                'warn.staffDetails',
                sprintf('У %d из %d сотрудников не заполнены направление подготовки / специальность или квалификация', count($thin), count($pedagogical)),
                false,
                is_string($first) && $first !== '' ? "/admin/team/$first/" : '/admin/team/',
                'warning',
                'sostav'
            );
        }

        foreach ($documents as $doc) {
            $date = $doc['date'] ?? null;
            if (!in_array($doc['section'] ?? null, self::AGING_DOCUMENTS, true) || PageData::docFile($doc) === null || !is_string($date) || preg_match(self::DATE, $date) !== 1) {
                continue;
            }
            $age = (int) (new DateTimeImmutable($date))->diff(new DateTimeImmutable($today))->format('%r%a');
            if ($age > self::OLD_AFTER_DAYS) {
                $add(
                    'warn.oldDocument:' . self::str($doc['id'] ?? null),
                    sprintf('Документу «%s» от %s больше года: проверьте, что он актуален', self::str($doc['title'] ?? null), Dates::ru($date)),
                    false,
                    '/admin/documents/' . self::str($doc['id'] ?? null) . '/',
                    'warning',
                    'dokumenty'
                );
            }
        }

        return $out;
    }

    /** Все обязательные пункты выполнены. @param list<array{level:string, ok:bool}> $items */
    public static function isReady(array $items): bool
    {
        foreach ($items as $item) {
            if ($item['level'] === 'required' && !$item['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Смены и базы, о которых говорит раздел сведений (его страницы берут те же данные).
     *
     * @param list<array<string,mixed>> $shifts
     * @param list<array<string,mixed>> $venues
     * @return array{season:list<array<string,mixed>>, venues:list<array<string,mixed>>, unlinked:list<array<string,mixed>>}
     *         season — нескрытые смены с началом в этом или следующем году (в том числе уже прошедшие); venues — их базы (нет смен — базы нескрытых смен);
     *         unlinked — смены, у которых база не выбрана или не найдена
     */
    public static function scope(array $shifts, array $venues, DateTimeImmutable $now): array
    {
        $year = (int) $now->format('Y');
        // п. 8 б — даты смен «на календарный год»: смены года остаются в списке и после окончания
        $season = array_values(array_filter($shifts, static fn (array $s): bool => empty($s['hidden'])
            && is_string($s['dateStart'] ?? null) && is_string($s['dateEnd'] ?? null)
            && preg_match(self::DATE, $s['dateStart']) === 1 && preg_match(self::DATE, $s['dateEnd']) === 1
            && in_array((int) substr($s['dateStart'], 0, 4), [$year, $year + 1], true)));
        usort($season, static fn (array $a, array $b): int => strcmp($a['dateStart'], $b['dateStart']));
        $basis = $season !== [] ? $season : array_values(array_filter($shifts, static fn (array $s): bool => empty($s['hidden'])));

        $bySlug = [];
        foreach ($venues as $v) {
            $bySlug[self::str($v['slug'] ?? null)] = $v;
        }
        $used = [];
        $unlinked = [];
        foreach ($basis as $shift) {
            $slug = self::str($shift['venue'] ?? null);
            if ($slug !== '' && isset($bySlug[$slug])) {
                $used[$slug] = true;
            } else {
                $unlinked[] = $shift;
            }
        }

        return [
            'season' => $season,
            'venues' => array_values(array_filter($venues, static fn (array $v): bool => isset($used[self::str($v['slug'] ?? null)]))),
            'unlinked' => $unlinked,
        ];
    }

    /** Заполнено ли значение: непустая строка (у richtext — не только теги), непустой список строк, число. */
    public static function filled(mixed $value): bool
    {
        if (is_string($value)) {
            $plain = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\u{00A0}");

            return $plain !== '';
        }
        if (is_array($value)) {
            return PageData::lines($value) !== [];
        }

        return is_int($value) || is_float($value);
    }

    /**
     * Предупреждения о пустых необязательных пунктах: id opt.{пункт}[:{база}], запуск не блокируют.
     *
     * @param array<string,mixed> $def
     * @param array<string,mixed> $texts
     * @param array<string,mixed> $settings
     * @param list<array<string,mixed>> $venues
     */
    private static function optionalWarnings(array $def, \Closure $add, array $texts, array $settings, array $venues, \Closure $docsOf): void
    {
        [$kind, $source] = explode(':', $def['source'], 2);
        $section = explode('.', $def['id'])[0];
        $label = static fn (string $suffix = ''): string => sprintf('Пункт %s пуст%s: «%s» — заполните или подтвердите, что не применяется', $def['paragraph'], $suffix, $def['title']);
        $id = 'opt.' . $def['id'];

        switch ($kind) {
            case 'texts':
                if (!self::filled($texts[$source] ?? null)) {
                    $add($id, $label(), false, '/admin/texts/' . (self::registry()[$source]['group'] ?? 'svedeniya') . '/#' . $source, 'warning', $section);
                }
                break;
            case 'settings':
                if (!self::filled($settings[$source] ?? null)) {
                    $add($id, $label(), false, '/admin/settings/#' . $source, 'warning', $section);
                }
                break;
            case 'documents':
                if ($docsOf($source) === []) {
                    $add($id, $label(), false, '/admin/documents/new/?section=' . $source, 'warning', $section);
                }
                break;
            case 'venues':
                foreach ($venues as $venue) {
                    if (!self::filled($venue[$source] ?? null)) {
                        $add($id . ':' . self::str($venue['slug'] ?? null), $label(' (' . self::str($venue['name'] ?? null) . ')'), false, '/admin/venues/' . self::str($venue['id'] ?? null) . '/#' . $source, 'warning', $section);
                    }
                }
                break;
        }
    }

    /** @return list<array<string,mixed>> пункты 3–11 приказа */
    public static function order(): array
    {
        return self::$order ??= require dirname(__DIR__, 2) . '/config/order201.php';
    }

    /** @param mixed $v коллекция `{items}` или готовый список @return list<array<string,mixed>> */
    private static function rows(mixed $v): array
    {
        if (!is_array($v)) {
            return [];
        }

        return PageData::items(array_key_exists('items', $v) ? $v : ['items' => $v]);
    }

    private static function str(mixed $v): string
    {
        return is_string($v) ? trim($v) : '';
    }

    /** @return array<string,array<string,mixed>> */
    public static function registry(): array
    {
        return self::$registry ??= require dirname(__DIR__, 2) . '/config/texts.php';
    }
}
