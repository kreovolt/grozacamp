<?php
declare(strict_types=1);

namespace Groza\Pages;

use DateTimeImmutable;
use Groza\Support\Sanitizer;
use Groza\App;
use Groza\Content\Nav;
use Groza\Content\PageData;
use Groza\Content\Staff;
use Groza\Domain\Shifts;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Readiness\Readiness;
use Groza\Support\Dates;
use Groza\Support\Phone;

use function Groza\View\e;
use function Groza\View\pending;
use function Groza\View\tel;

/**
 * Раздел «Сведения об организации отдыха детей и их оздоровления» (спека §7.6, Приказ Минпросвещения № 201):
 * хаб и шесть страниц. Каждый пункт приказа выводится его дословной формулировкой (config/order201.php) и данными из админки;
 * пустое обязательное — «Информация обновляется»; пустой необязательный («при наличии») пункт на странице не выводится
 * (о нём предупреждает Readiness).
 * Смены, цены и базы берутся из данных смен и баз, не из текстов. HTML значений собирают только этот класс
 * и components/*: пользовательские строки экранируются, richtext уже очищен админкой.
 */
final class SvedeniyaPage
{
    private const NBSP = "\u{00A0}";
    private const ALL_DATA = ['texts', 'settings', 'documents', 'team', 'venues', 'shifts'];

    /** страница → [H1 дословно из приказа, метаописание, файлы данных, из которых страница берёт «Обновлено»] */
    private const PAGES = [
        'hub' => ['Сведения об организации отдыха детей и их оздоровления', 'Официальная информация о детском лагере «Гроза»: наименование, документы, руководство, состав, деятельность, оснащение, услуги и доступная среда.', self::ALL_DATA],
        'ob-organizacii' => ['Об организации отдыха детей и их оздоровления', 'Основные сведения о детском лагере «Гроза»: наименование, руководитель, адреса, режим работы, документы, руководство, педагогический и вожатский состав, контакты.', self::ALL_DATA],
        'deyatelnost' => ['Деятельность', 'Возраст детей, даты смен на календарный год, образовательные и оздоровительные программы детского лагеря «Гроза».', ['texts', 'shifts', 'venues']],
        'mto' => ['Материально-техническое обеспечение и оснащенность организации отдыха детей и их оздоровления', 'Условия проживания и питания, учебные кабинеты, библиотека, спортивные объекты баз, где проходят смены детского лагеря «Гроза».', ['venues', 'shifts']],
        'uslugi' => ['Услуги, в том числе платные, предоставляемые организацией отдыха детей и их оздоровления', 'Порядок оказания платных услуг, стоимость путёвки и одного дня, компенсация, документы для зачисления и список вещей — детский лагерь «Гроза».', ['texts', 'shifts']],
        'dostupnaya-sreda' => ['Доступная среда', 'Условия отдыха и оздоровления детей с ограниченными возможностями здоровья и детей-инвалидов на базах детского лагеря «Гроза».', ['venues', 'shifts']],
        'inaya-informaciya' => ['Иная информация', 'Дополнительные сведения и документы детского лагеря «Гроза».', ['texts', 'documents']],
    ];

    private function __construct(private App $app, private string $page)
    {
    }

    public static function hub(App $app): self
    {
        return new self($app, 'hub');
    }

    public static function obOrganizacii(App $app): self
    {
        return new self($app, 'ob-organizacii');
    }

    public static function deyatelnost(App $app): self
    {
        return new self($app, 'deyatelnost');
    }

    public static function mto(App $app): self
    {
        return new self($app, 'mto');
    }

    public static function uslugi(App $app): self
    {
        return new self($app, 'uslugi');
    }

    public static function dostupnaya(App $app): self
    {
        return new self($app, 'dostupnaya-sreda');
    }

    public static function inaya(App $app): self
    {
        return new self($app, 'inaya-informaciya');
    }

    /** @param array<string,string> $params */
    public function __invoke(Request $r, array $params): Response
    {
        [$title, $description, $files] = self::PAGES[$this->page];
        $ctx = $this->context();
        $vars = match ($this->page) {
            'ob-organizacii' => $this->buildOb($ctx),
            'deyatelnost' => $this->buildDeyatelnost($ctx),
            'mto' => $this->buildMto($ctx),
            'uslugi' => $this->buildUslugi($ctx),
            'dostupnaya-sreda' => $this->buildDostupnaya($ctx),
            'inaya-informaciya' => $this->buildInaya($ctx),
            default => ['sections' => []],
        };
        $vars['sections'] = array_values(array_filter($vars['sections'], static fn (array $s): bool => $s['rows'] !== []));
        $toc = $this->toc($vars['sections']);
        $updated = $this->updated($files);

        return Response::html($this->app->view()->render('pages/svedeniya/' . $this->page, $vars + [
            'title' => $title,
            'description' => $description,
            'crumbs' => $this->crumbs($title),
            'toc' => count($toc) >= 2 ? $toc : [],
            'updated' => $updated?->format('Y-m-d'),
            'updatedText' => $updated !== null ? Dates::ru($updated->format('Y-m-d')) : '',
            'tree' => $this->svedeniyaNav()['children'] ?? [],
        ]));
    }

    // ------------------------------------------------------------------ страницы

    /** @param array<string,mixed> $c @return array<string,mixed> */
    private function buildOb(array $c): array
    {
        $texts = $c['texts'];
        $settings = $c['settings'];

        $osnovnye = $this->rows([
            $this->row($c, 'osnovnye.a', $this->fullName($c)),
            $this->row($c, 'osnovnye.b', self::plain($texts['sved.director'] ?? null)),
            $this->row($c, 'osnovnye.v', self::plain($texts['sved.legalForm'] ?? null)),
            $this->row($c, 'osnovnye.g', self::plain($texts['sved.campType'] ?? null)),
            $this->row($c, 'osnovnye.d', self::bullets($this->addresses($c))),
            $this->row($c, 'osnovnye.e', self::rich($texts['sved.mode'] ?? null)),
        ]);

        $subDocs = fn (string $section, string $label): string => '<p class="font-semibold text-ink">' . e($label) . '</p>'
            . ($this->docList($c['documents'], [$section]) ?: '<p>' . pending('') . '</p>');
        $rulesPage = Readiness::filled($texts['rules'] ?? null) ? '<p><a href="/pravila/">Правила пребывания в лагере</a> (страница сайта)</p>' : '';
        $dokumenty = $this->rows([
            $this->row($c, 'dokumenty.a', $this->docList($c['documents'], ['uchreditelnye'])),
            $this->row($c, 'dokumenty.b', ($this->docList($c['documents'], ['pravila']) ?: '<p>' . pending('') . '</p>') . $rulesPage),
            $this->row($c, 'dokumenty.v', $subDocs('programma-vospitaniya', 'Программа воспитательной работы') . $subDocs('kalendarnyj-plan', 'Календарный план воспитательной работы')),
            $this->row($c, 'dokumenty.g', $this->docList($c['documents'], ['programma-razvitiya'])),
        ]);

        $leaders = Staff::pick($c['team'], ['leadership']);
        $pedagogical = Staff::pick($c['team'], Staff::PEDAGOGICAL);
        $ovz = array_values(array_filter(Staff::all($c['team']), static fn (array $m): bool => !empty($m['ovzResponsible'])));

        $phones = '';
        foreach (['phone' => '', 'whatsapp' => ' (WhatsApp)'] as $key => $note) {
            $number = self::str($settings[$key] ?? null);
            if ($number !== '') {
                $phones .= '<li><a class="tabular whitespace-nowrap" href="' . e(tel($number)) . '">' . e(Phone::pretty($number)) . '</a>' . e($note) . '</li>';
            }
        }
        $hours = array_filter([self::str($settings['hoursWeekdays'] ?? null), self::str($settings['hoursWeekend'] ?? null)]);
        $email = self::str($settings['email'] ?? null);

        return ['sections' => [
            ['id' => 'osnovnye-svedeniya', 'title' => 'Основные сведения', 'rows' => $osnovnye],
            ['id' => 'dokumenty', 'title' => 'Документы', 'rows' => $dokumenty],
            ['id' => 'rukovodstvo', 'title' => 'Руководство', 'rows' => $this->rows([$this->row($c, 'rukovodstvo.a', $this->staffTable($leaders, 'short'), true)])],
            ['id' => 'sostav', 'title' => 'Педагогический и вожатский состав', 'rows' => $this->rows([
                $this->row($c, 'sostav.a', $this->staffTable($pedagogical, 'full'), true),
                $this->row($c, 'sostav.b', $this->staffTable($ovz, 'short'), true),
            ])],
            ['id' => 'kontakty', 'title' => 'Контакты', 'rows' => $this->rows([
                $this->row($c, 'kontakty.a', $phones === '' ? '' : '<ul>' . $phones . '</ul>' . ($hours !== [] ? '<p class="text-ink-soft">Звонки в офис: ' . e(implode(', ', $hours)) . '</p>' : '')),
                $this->row($c, 'kontakty.b', $email === '' ? '' : '<a href="mailto:' . e($email) . '">' . e($email) . '</a>'),
                $this->row($c, 'kontakty.v', self::plain($settings['postalAddress'] ?? null)),
            ])],
        ]];
    }

    /** @param array<string,mixed> $c @return array<string,mixed> */
    private function buildDeyatelnost(array $c): array
    {
        $texts = $c['texts'];
        $table = $c['season'] === [] ? '' : $this->app->view()->partial('components/shifts-table', [
            'mode' => 'dates',
            'shifts' => $this->shiftRows($c, $c['season']),
            'caption' => 'Даты смен на ' . $this->years($c['season']),
        ]);

        return ['sections' => [
            ['id' => 'vozrast', 'title' => 'Возрастная категория детей', 'rows' => $this->rows([$this->row($c, 'deyatelnost.a', self::rich($texts['sved.ages'] ?? null))])],
            ['id' => 'smeny', 'title' => 'Даты смен', 'rows' => $this->rows([$this->row($c, 'deyatelnost.b', $table, true)])],
            ['id' => 'programmy', 'title' => 'Программы и методические разработки', 'rows' => $this->rows([
                $this->row($c, 'deyatelnost.v', self::rich($texts['sved.eduPrograms'] ?? null)),
                $this->row($c, 'deyatelnost.g', self::rich($texts['sved.healthPrograms'] ?? null)),
                $this->row($c, 'deyatelnost.d', self::rich($texts['sved.methods'] ?? null)),
            ])],
        ]];
    }

    /** @param array<string,mixed> $c @return array<string,mixed> */
    private function buildMto(array $c): array
    {
        $sections = [];
        foreach ($c['scope']['venues'] as $venue) {
            $rows = [];
            foreach (['a' => 'mto_a', 'b' => 'mto_b', 'v' => 'mto_v', 'g' => 'mto_g', 'd' => 'mto_d'] as $letter => $field) {
                $rows[] = $this->row($c, 'mto.' . $letter, self::block($venue[$field] ?? null));
            }
            $sections[] = $this->venueSection($venue, $this->rows($rows));
        }

        return ['sections' => $sections];
    }

    /** @param array<string,mixed> $c @return array<string,mixed> */
    private function buildDostupnaya(array $c): array
    {
        $sections = [];
        foreach ($c['scope']['venues'] as $venue) {
            $rows = [];
            foreach (array_keys($c['order']) as $id) {
                if (str_starts_with($id, 'dostupnaya.')) {
                    $rows[] = $this->row($c, $id, self::block($venue['access_' . substr($id, strlen('dostupnaya.'))] ?? null));
                }
            }
            $sections[] = $this->venueSection($venue, $this->rows($rows));
        }

        return ['sections' => $sections];
    }

    /** @param array<string,mixed> $c @return array<string,mixed> */
    private function buildUslugi(array $c): array
    {
        $texts = $c['texts'];
        $rows = $this->shiftRows($c, $c['season']);
        $table = $rows === [] ? '' : $this->app->view()->partial('components/shifts-table', [
            'mode' => 'prices',
            'shifts' => $rows,
            'caption' => 'Стоимость путёвок на ' . $this->years($c['season']),
        ]) . $this->averageLine($rows);

        return ['sections' => [
            ['id' => 'poryadok', 'title' => 'Порядок оказания платных услуг', 'rows' => $this->rows([$this->row($c, 'uslugi.a', self::rich($texts['sved.paidOrder'] ?? null))])],
            ['id' => 'stoimost', 'title' => 'Стоимость дня и путёвки', 'rows' => $this->rows([$this->row($c, 'uslugi.b', $table, true)])],
            ['id' => 'kompensaciya', 'title' => 'Компенсация стоимости', 'rows' => $this->rows([$this->row($c, 'uslugi.v', self::rich($texts['sved.compensation'] ?? null))])],
            ['id' => 'dokumenty-zachisleniya', 'title' => 'Документы для зачисления', 'rows' => $this->rows([$this->row($c, 'uslugi.g', self::bullets(PageData::lines($texts['lists.documents'] ?? null)))])],
            ['id' => 'chto-vzyat', 'title' => 'Одежда, обувь и гигиенические принадлежности', 'rows' => $this->rows([$this->row($c, 'uslugi.d', self::bullets(PageData::lines($texts['lists.packing'] ?? null)))])],
        ]];
    }

    /** @param array<string,mixed> $c @return array<string,mixed> */
    private function buildInaya(array $c): array
    {
        return [
            'sections' => [],
            'other' => self::rich($c['texts']['sved.other'] ?? null),
            'docs' => $this->docList($c['documents'], ['inoe']),
        ];
    }

    // ------------------------------------------------------------------ данные

    /** @return array<string,mixed> */
    private function context(): array
    {
        $data = Readiness::dataFrom($this->app);
        $shifts = PageData::items($data['shifts']);
        $venues = PageData::items($data['venues']);
        $scope = Readiness::scope($shifts, $venues, $this->app->clock()->now());

        return [
            'settings' => $data['settings'],
            'texts' => $data['texts'],
            'documents' => PageData::byOrder(PageData::items($data['documents'])),
            'team' => PageData::items($data['team']),
            'venues' => $venues,
            'scope' => $scope,
            'season' => $scope['season'],
            'order' => array_column(Readiness::order(), null, 'id'),
        ];
    }

    /**
     * Строка пункта приказа: номер, формулировка и значение. Пустое значение обязательного пункта — «Информация обновляется»;
     * пустой необязательный («при наличии») пункт не выводится (null). $wide — значение под формулировкой, на всю ширину (таблицы).
     *
     * @param array<string,mixed> $c
     * @return array{item:string,paragraph:string,full:string,value:string,wide:bool}|null
     */
    private function row(array $c, string $id, string $value, bool $wide = false): ?array
    {
        $def = $c['order'][$id];
        if (trim($value) === '' && $def['level'] !== 'required') {
            return null;
        }

        return ['item' => $id, 'paragraph' => $def['paragraph'], 'full' => $def['full'], 'value' => trim($value) === '' ? pending('') : $value, 'wide' => $wide];
    }

    /** Строки без пропущенных необязательных пунктов. @param list<array<string,mixed>|null> $rows @return list<array<string,mixed>> */
    private function rows(array $rows): array
    {
        return array_values(array_filter($rows));
    }

    /** @param array<string,mixed> $c */
    private function fullName(array $c): string
    {
        $full = self::plain($c['texts']['sved.fullName'] ?? null);
        $short = self::plain($c['texts']['sved.shortName'] ?? null);

        return $full === '' ? '' : $full . ($short !== '' ? '<p class="mt-1 text-ink-soft">Сокращённое наименование: ' . $short . '</p>' : '');
    }

    /** Адреса: список из админки, а без него — адрес офиса и базы сезона. @param array<string,mixed> $c @return list<string> */
    private function addresses(array $c): array
    {
        $own = PageData::lines($c['texts']['sved.addresses'] ?? null);
        if ($own !== []) {
            return $own;
        }
        $out = [];
        $office = self::str($c['settings']['officeAddress'] ?? null);
        if ($office !== '') {
            $out[] = 'Офис: ' . $office;
        }
        foreach ($c['scope']['venues'] as $venue) {
            $line = trim(self::str($venue['name'] ?? null) . ', ' . self::str($venue['address'] ?? null), ' ,');
            if ($line !== '') {
                $out[] = 'Место проведения смен: ' . $line;
            }
        }

        return $out;
    }

    /** @param list<array<string,mixed>> $docs @param list<string> $sections */
    private function docList(array $docs, array $sections): string
    {
        $items = [];
        foreach ($docs as $doc) {
            $file = PageData::docFile($doc);
            if (!in_array($doc['section'] ?? null, $sections, true) || $file === null) {
                continue;
            }
            $date = self::str($doc['date'] ?? null);
            $items[] = [
                'title' => self::str($doc['title'] ?? null) ?: 'Документ',
                'url' => self::str($file['url']),
                'meta' => self::fileMeta(self::str($file['url']), (int) ($file['bytes'] ?? 0)),
                'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? Dates::ru($date) : '',
            ];
        }

        return $items === [] ? '' : $this->app->view()->partial('components/doc-list', ['docs' => $items]);
    }

    /** @param list<array<string,mixed>> $members */
    private function staffTable(array $members, string $columns): string
    {
        return $members === [] ? '' : $this->app->view()->partial('components/staff-table', ['members' => $members, 'columns' => $columns]);
    }

    /**
     * @param array<string,mixed> $c
     * @param list<array<string,mixed>> $shifts
     * @return list<array{title:string,note:string,dates:string,days:int,ages:string,venue:string,price:?int,perDay:?int}>
     */
    private function shiftRows(array $c, array $shifts): array
    {
        $names = [];
        foreach ($c['venues'] as $v) {
            $names[self::str($v['slug'] ?? null)] = self::str($v['name'] ?? null);
        }
        $out = [];
        foreach ($shifts as $s) {
            $days = Shifts::days((string) $s['dateStart'], (string) $s['dateEnd']);
            $price = PageData::price($s['price'] ?? null);
            $min = (int) ($s['ageMin'] ?? 0);
            $max = (int) ($s['ageMax'] ?? 0);
            $out[] = [
                'title' => self::str($s['title'] ?? null),
                'note' => self::str($s['priceNote'] ?? null),
                'dates' => Dates::range((string) $s['dateStart'], (string) $s['dateEnd']),
                'days' => $days,
                'ages' => $min > 0 && $max > 0 ? ($min === $max ? (string) $max : $min . '–' . $max) . ' ' . self::plural($max, 'год', 'года', 'лет') : '',
                'venue' => $names[self::str($s['venue'] ?? null)] ?? '',
                'price' => $price,
                'perDay' => Shifts::costPerDay($price, $days),
            ];
        }

        return $out;
    }

    /** Средняя стоимость дня по сменам с ценой (п. 10 б). @param list<array{days:int,price:?int}> $rows */
    private function averageLine(array $rows): string
    {
        $priced = array_values(array_filter($rows, static fn (array $r): bool => $r['price'] !== null));
        if (count($priced) < 2) {
            return '';
        }
        $avg = Shifts::costPerDay((int) array_sum(array_column($priced, 'price')), (int) array_sum(array_column($priced, 'days')));

        return '<p class="mt-4">В среднем по сменам: ' . e(self::perDay((int) $avg)) . '</p>';
    }

    /** «2027 год» / «2027 и 2028 годы» @param list<array<string,mixed>> $shifts */
    private function years(array $shifts): string
    {
        $years = array_values(array_unique(array_map(static fn (array $s): string => substr((string) $s['dateStart'], 0, 4), $shifts)));

        return implode(' и ', $years) . (count($years) > 1 ? ' годы' : ' год');
    }

    /** @param array<string,mixed> $venue @param list<array<string,mixed>> $rows @return array<string,mixed> */
    private function venueSection(array $venue, array $rows): array
    {
        return [
            'id' => self::str($venue['slug'] ?? null),
            'title' => self::str($venue['name'] ?? null),
            'lead' => self::str($venue['address'] ?? null),
            'rows' => $rows,
        ];
    }

    /** @param list<array<string,mixed>> $sections @return list<array{id:string,label:string}> */
    private function toc(array $sections): array
    {
        return array_map(static fn (array $s): array => ['id' => (string) $s['id'], 'label' => (string) $s['title']], $sections);
    }

    /** Самая поздняя правка файлов данных, которые читает страница. @param list<string> $names */
    private function updated(array $names): ?DateTimeImmutable
    {
        $latest = null;
        foreach ($names as $name) {
            $at = $this->app->store()->updatedAt($name);
            if ($at !== null && ($latest === null || $at > $latest)) {
                $latest = $at;
            }
        }

        return $latest;
    }

    /** @return list<array{label:string,href?:string}> */
    private function crumbs(string $title): array
    {
        $section = $this->svedeniyaNav();
        $crumbs = [['label' => 'Главная', 'href' => '/']];
        if ($this->page === 'hub') {
            return [...$crumbs, ['label' => $section['label']]];
        }
        $crumbs[] = ['label' => $section['label'], 'href' => '/svedeniya/'];
        foreach ($section['children'] ?? [] as $child) {
            if ($child['href'] === '/svedeniya/' . $this->page . '/') {
                return [...$crumbs, ['label' => $child['label']]];
            }
        }

        return [...$crumbs, ['label' => $title]];
    }

    /** @return array<string,mixed> */
    private function svedeniyaNav(): array
    {
        foreach (Nav::main() as $item) {
            if ($item['href'] === '/svedeniya/') {
                return $item;
            }
        }

        return ['label' => self::PAGES['hub'][0], 'href' => '/svedeniya/', 'children' => []];
    }

    // ------------------------------------------------------------------ HTML значений (пользовательское экранируется)

    private static function str(mixed $v): string
    {
        return is_string($v) ? trim($v) : '';
    }

    /** Строка из админки → экранированный текст ('' если пусто). */
    private static function plain(mixed $v): string
    {
        return e(self::str($v));
    }

    /** Richtext из админки (очищен при сохранении) как есть; пустая разметка → ''. */
    private static function rich(mixed $v): string
    {
        return is_string($v) && Readiness::filled($v) ? Sanitizer::clean($v) : '';
    }

    /** Текст многострочного поля: абзацы по пустой строке, переносы внутри абзаца. */
    private static function block(mixed $v): string
    {
        $text = self::str($v);
        if ($text === '') {
            return '';
        }
        $paras = preg_split('/\R{2,}/u', $text) ?: [];

        return implode('', array_map(static fn (string $p): string => '<p>' . nl2br(e(trim($p)), false) . '</p>', $paras));
    }

    /** @param list<string> $lines */
    private static function bullets(array $lines): string
    {
        return $lines === [] ? '' : '<ul>' . implode('', array_map(static fn (string $l): string => '<li>' . e($l) . '</li>', $lines)) . '</ul>';
    }

    /** «PDF, 1,2 МБ»: тип по расширению файла, размер в КБ или МБ. */
    public static function fileMeta(string $url, int $bytes): string
    {
        $ext = strtoupper(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $type = $ext !== '' ? $ext : 'PDF';
        if ($bytes <= 0) {
            return $type;
        }
        $size = $bytes >= 1048576
            ? number_format($bytes / 1048576, 1, ',', '') . self::NBSP . 'МБ'
            : max(1, (int) round($bytes / 1024)) . self::NBSP . 'КБ';

        return $type . ', ' . $size;
    }

    /** «≈ 2 857 ₽ в день»: разряды и знак рубля неразрывными пробелами. */
    public static function perDay(int $rub): string
    {
        return '≈' . self::NBSP . self::rub($rub) . ' в' . self::NBSP . 'день';
    }

    /** «60 000 ₽» */
    public static function rub(int $rub): string
    {
        return number_format($rub, 0, '', self::NBSP) . self::NBSP . '₽';
    }

    private static function plural(int $n, string $one, string $few, string $many): string
    {
        $n = abs($n) % 100;
        $d = $n % 10;
        if ($n > 10 && $n < 20) {
            return $many;
        }

        return $d === 1 ? $one : ($d >= 2 && $d <= 4 ? $few : $many);
    }
}
