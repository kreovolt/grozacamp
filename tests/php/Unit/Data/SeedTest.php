<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Data;

use DateTimeImmutable;
use Groza\App;
use Groza\Data\JsonStore;
use Groza\Data\Seeder;
use Groza\Domain\Collections;
use Groza\Domain\Shifts;
use Groza\Domain\ValidationException;
use Groza\Domain\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SeedTest extends TestCase
{
    private const META = ['id', 'updatedAt', 'updatedBy'];
    private const RICH_TAGS = ['p', 'br', 'strong', 'em', 'a', 'ul', 'ol', 'li', 'h3', 'blockquote'];
    private const FAKE_NOW = '2027-01-15T12:00:00+05:00';
    private const DEMO_SEASON = 'Лето 2027 (пример)';

    /** @var list<string> */
    private array $tmpDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpDirs as $dir) {
            self::removeDir($dir);
        }
    }

    /** @return array<string,array{string}> */
    public static function seedSets(): array
    {
        return ['seed/data' => ['data'], 'seed/demo' => ['demo']];
    }

    #[DataProvider('seedSets')]
    public function test_every_seed_collection_validates(string $set): void
    {
        $venueSlugs = array_column(self::read($set, 'venues')['items'], 'slug');

        foreach (Collections::all() as $name => $def) {
            $file = self::read($set, $name);
            $this->assertArrayHasKey('items', $file, "$set/$name.json");
            $this->assertIsList($file['items'], "$set/$name.json: items — список");

            foreach ($file['items'] as $i => $item) {
                $where = "$set/$name.json #$i";
                foreach (self::META as $key) {
                    $this->assertIsString($item[$key] ?? null, "$where: $key");
                    $this->assertNotSame('', $item[$key], "$where: $key");
                }
                $this->assertMatchesRegularExpression('/^[a-f0-9]{12}$/', $item['id'], "$where: id как у админки");

                try {
                    $clean = Validator::validate($def, $item, ['venues' => $venueSlugs]);
                } catch (ValidationException $e) {
                    $this->fail("$where: " . json_encode($e->errors(), JSON_UNESCAPED_UNICODE));
                }
                $record = array_diff_key($item, array_flip(self::META));
                ksort($record);
                ksort($clean);
                $this->assertSame($clean, $record, "$where: запись совпадает с тем, что сохранила бы админка");
            }

            $this->assertSame(
                count($file['items']),
                count(array_unique(array_column($file['items'], 'id'))),
                "$set/$name.json: id уникальны"
            );
            if (in_array('slug', array_column($def['fields'], 'name'), true)) {
                $slugs = array_column($file['items'], 'slug');
                $this->assertSame(count($slugs), count(array_unique($slugs)), "$set/$name.json: slug уникальны");
            }
        }
    }

    public function test_text_registry_keys_types_and_groups(): void
    {
        $registry = self::registry();
        $expected = [
            'home.heroKicker', 'home.heroTitle', 'home.heroLead', 'home.stats', 'home.included', 'home.schedule',
            'info.prepare', 'info.contraindications', 'info.medicines', 'info.contact', 'info.transfer', 'info.visits',
            'lists.packing', 'lists.documents', 'rules', 'policy',
            'sved.campType', 'sved.legalForm', 'sved.fullName', 'sved.shortName', 'sved.director', 'sved.addresses',
            'sved.mode', 'sved.ages', 'sved.eduPrograms', 'sved.healthPrograms', 'sved.methods', 'sved.paidOrder',
            'sved.compensation', 'sved.other',
            'vacancies.intro', 'vacancies.benefits', 'vacancies.selection',
        ];
        $this->assertSame($expected, array_keys($registry));

        foreach ($registry as $key => $def) {
            $this->assertIsString($def['label'] ?? null, $key);
            $this->assertNotSame('', $def['label'], $key);
            $this->assertContains($def['type'] ?? null, ['plain', 'richtext', 'list'], $key);
            $group = match (true) {
                str_starts_with($key, 'home.') => 'home',
                str_starts_with($key, 'info.'), str_starts_with($key, 'lists.'), $key === 'rules' => 'info',
                str_starts_with($key, 'sved.') => 'svedeniya',
                str_starts_with($key, 'vacancies.') => 'vacancies',
                $key === 'policy' => 'legal',
            };
            $this->assertSame($group, $def['group'] ?? null, $key);
        }

        foreach (['home.stats', 'home.included', 'home.schedule', 'lists.packing', 'lists.documents', 'sved.addresses'] as $key) {
            $this->assertSame('list', $registry[$key]['type'], $key);
        }
        $this->assertSame('richtext', $registry['rules']['type']);
        $this->assertSame('richtext', $registry['policy']['type']);
    }

    public function test_text_registry_required_paragraphs_of_order_201(): void
    {
        $required = array_filter(array_map(static fn (array $d) => $d['required'] ?? null, self::registry()));

        $this->assertSame([
            'lists.packing' => '10.д',
            'lists.documents' => '10.г',
            'rules' => '4.б',
            'sved.campType' => '3.г',
            'sved.legalForm' => '3.в',
            'sved.fullName' => '3.а',
            'sved.director' => '3.б',
            'sved.addresses' => '3.д',
            'sved.mode' => '3.е',
            'sved.ages' => '8.а',
            'sved.paidOrder' => '10.а',
        ], $required);
    }

    #[DataProvider('seedSets')]
    public function test_texts_json_has_every_registry_key(string $set): void
    {
        $texts = self::read($set, 'texts');

        foreach (self::registry() as $key => $def) {
            $this->assertArrayHasKey($key, $texts, "$set/texts.json");
            if ($def['type'] === 'list') {
                $this->assertIsList($texts[$key], "$set: $key — список строк");
                foreach ($texts[$key] as $line) {
                    $this->assertIsString($line, "$set: $key");
                    $this->assertSame(trim($line), $line, "$set: $key");
                    $this->assertNotSame('', $line, "$set: $key");
                }
            } else {
                $this->assertIsString($texts[$key], "$set: $key — строка");
            }
        }
        $this->assertSame([], array_diff(array_keys($texts), array_keys(self::registry())), "$set: лишние ключи");
    }

    public function test_home_texts_from_brief(): void
    {
        $texts = self::read('data', 'texts');

        $this->assertSame('', $texts['home.heroKicker'], 'пусто — кикер берётся из ближайшего сезона');
        $this->assertSame('Лагерь настоящих приключений', $texts['home.heroTitle']);
        $this->assertSame([
            '2016|работаем с этого года',
            '40+|проведённых смен',
            'до 24|детей в отряде, 2 вожатых',
            '5 раз|питание в день',
        ], $texts['home.stats']);
        $this->assertSame('Лучников Евгений Алексеевич', $texts['sved.director']);
        foreach ($texts['home.schedule'] as $line) {
            $this->assertMatchesRegularExpression('/^\d\d:\d\d(–\d\d:\d\d)?\|\S/u', $line, 'время|дело');
        }
    }

    #[DataProvider('seedSets')]
    public function test_settings_keep_default_contact_keys(string $set): void
    {
        $defaults = require self::root() . '/config/settings.defaults.php';
        $settings = self::read($set, 'settings');

        foreach (array_keys($defaults) as $key) {
            $this->assertArrayHasKey($key, $settings, "$set/settings.json");
        }
        $this->assertSame('https://vk.com/topic-185063757_43871120', $settings['vkReviewsUrl']);
        $this->assertSame('2474347@bk.ru', $settings['mailTo']);
        $this->assertArrayHasKey('postalAddress', $settings);
        $this->assertArrayHasKey('metrikaId', $settings);
        if ($set === 'data') {
            $this->assertSame($defaults, array_intersect_key($settings, $defaults), 'контакты — как в Global Constraints');
            $this->assertSame('', $settings['postalAddress'], 'почтовый адрес не подтверждён');
            $this->assertSame('', $settings['metrikaId']);
        }
    }

    public function test_demo_has_open_shifts_after_fake_now(): void
    {
        $items = self::read('demo', 'shifts')['items'];
        $now = new DateTimeImmutable(self::FAKE_NOW);
        $summer = array_values(array_filter(
            Shifts::visible($items, $now),
            static fn (array $s): bool => $s['season'] === self::DEMO_SEASON
        ));

        $this->assertGreaterThanOrEqual(3, count($summer));
        $this->assertSame(['open', 'few', 'closed'], array_column($summer, 'status'));
        $this->assertSame([60000, 60000, 40000], array_column($summer, 'price'));
        $this->assertSame(self::DEMO_SEASON, Shifts::nearestSeason($items, $now), 'кикер героя в тестах — летний сезон');
    }

    public function test_demo_shifts_from_brief(): void
    {
        $bySlug = array_column(self::read('demo', 'shifts')['items'], null, 'slug');

        $summer = array_values(array_filter($bySlug, static fn (array $s) => $s['season'] === self::DEMO_SEASON));
        $this->assertSame(
            [['2027-07-02', '2027-07-22'], ['2027-07-25', '2027-08-14'], ['2027-08-17', '2027-08-30']],
            array_map(static fn (array $s) => [$s['dateStart'], $s['dateEnd']], $summer)
        );
        foreach ($summer as $s) {
            $this->assertSame('solnyshko', $s['venue']);
            $this->assertSame([8, 17], [$s['ageMin'], $s['ageMax']]);
        }
        $this->assertArrayHasKey('spasatel-2027', $bySlug, 'на этот slug ссылаются тесты страниц и писем');
        $this->assertSame('Спасатель', $bySlug['spasatel-2027']['title']);
        $this->assertNotSame('closed', $bySlug['spasatel-2027']['status'], 'смену можно выбрать в форме');

        $spring = array_values(array_filter($bySlug, static fn (array $s) => $s['season'] === 'Весенние каникулы 2027 (пример)'));
        $this->assertCount(1, $spring);
        $this->assertSame('vostok', $spring[0]['venue']);
        $this->assertSame([7, 14], [$spring[0]['ageMin'], $spring[0]['ageMax']]);
        $this->assertLessThanOrEqual(6, $spring[0]['days'], 'короткая смена');
    }

    public function test_data_seed_has_no_demo_marks(): void
    {
        $files = glob(self::root() . '/seed/data/*.json') ?: [];
        $this->assertCount(9, $files);

        foreach ($files as $file) {
            $raw = (string) file_get_contents($file);
            $this->assertStringNotContainsString('(пример)', $raw, basename($file));
            $this->assertDoesNotMatchRegularExpression('/\[\p{Cyrillic}/u', $raw, basename($file) . ': заглушка в квадратных скобках');
        }
    }

    public function test_data_seed_keeps_unconfirmed_collections_empty(): void
    {
        foreach (['shifts', 'reviews', 'vacancies', 'documents'] as $name) {
            $this->assertSame(['items' => []], self::read('data', $name), "seed/data/$name.json");
        }
        $this->assertSame([], array_values(array_diff(
            array_map('basename', glob(self::root() . '/seed/demo/*.json') ?: []),
            array_map('basename', glob(self::root() . '/seed/data/*.json') ?: [])
        )), 'seed/demo — те же файлы, что seed/data');
    }

    public function test_data_team_from_old_site(): void
    {
        $team = self::read('data', 'team')['items'];
        $names = array_column($team, 'name');

        $this->assertCount(23, $team, '25 карточек старой /team, двое повторялись');
        $this->assertSame(count($names), count(array_unique($names)));
        $this->assertSame(['leadership'], array_values(array_unique(array_column(
            array_filter($team, static fn (array $m) => in_array($m['role'], ['Директор', 'Заместитель директора'], true)),
            'group'
        ))));
        $byName = array_column($team, null, 'name');
        $this->assertSame('Инструктор по НВП, вожатый', $byName['Эйзеле Максим Евгеньевич']['role']);
        foreach ($team as $m) {
            $this->assertSame(['', '', ''], [$m['education'], $m['specialty'], $m['qualification']], $m['name'] . ': образование не подтверждено');
            $this->assertNull($m['photo'], $m['name']);
            $this->assertFalse($m['ovzResponsible'], $m['name']);
        }
        $this->assertLessThanOrEqual(6, count(array_filter(array_column($team, 'onHome'))));
    }

    public function test_demo_team_has_partly_filled_education(): void
    {
        $education = array_column(self::read('demo', 'team')['items'], 'education');

        $this->assertNotEmpty(array_filter($education), 'у части команды образование заполнено');
        $this->assertContains('', $education, 'и у части — нет (проверка готовности)');
    }

    public function test_venues(): void
    {
        $photos = json_decode((string) file_get_contents(self::root() . '/config/photos.json'), true);
        $baseKeys = array_values(array_filter(array_keys($photos), static fn (string $k) => str_starts_with($k, 'base-')));

        foreach (['data', 'demo'] as $set) {
            $venues = array_column(self::read($set, 'venues')['items'], null, 'slug');
            $this->assertSame(['solnyshko', 'vostok'], array_keys($venues), $set);
            $this->assertSame('ДЗОЛ «Солнышко»', $venues['solnyshko']['name']);
            $this->assertSame('ДЗОЛ «Восток»', $venues['vostok']['name']);
            $this->assertStringContainsString('дер. Коляды', $venues['solnyshko']['address']);
            $this->assertStringContainsString('с. Усть-Качка', $venues['vostok']['address']);
            $this->assertSame($baseKeys, $venues['solnyshko']['photos'], $set);
            $this->assertSame([], $venues['vostok']['photos'], $set);

            foreach ($venues as $slug => $venue) {
                $extra = array_filter($venue, static fn ($v, $k) => preg_match('/^(mto|access)_/', $k) === 1 && $v !== '', ARRAY_FILTER_USE_BOTH);
                $expectFilled = $set === 'demo' && $slug === 'solnyshko';
                $this->assertSame($expectFilled ? 18 : 0, count($extra), "$set/$slug: МТО и доступная среда");
            }
        }
    }

    #[DataProvider('seedSets')]
    public function test_image_keys_exist_in_photo_manifest(string $set): void
    {
        $photos = json_decode((string) file_get_contents(self::root() . '/config/photos.json'), true);
        $keys = array_column(self::read($set, 'shifts')['items'], 'photo');
        foreach (self::read($set, 'venues')['items'] as $venue) {
            $keys = array_merge($keys, $venue['photos']);
        }

        foreach (array_filter($keys, 'is_string') as $key) {
            $this->assertArrayHasKey($key, $photos, "$set: фото $key");
        }
    }

    public function test_data_faq_has_eleven_questions_five_on_home(): void
    {
        $faq = self::read('data', 'faq')['items'];

        $this->assertCount(11, $faq);
        $this->assertCount(5, array_filter(array_column($faq, 'onHome')));
    }

    public function test_demo_documents_point_to_demo_files(): void
    {
        $docs = self::read('demo', 'documents')['items'];
        $this->assertCount(2, $docs);

        foreach ($docs as $doc) {
            $this->assertMatchesRegularExpression('#^/uploads/docs/demo-[a-z0-9-]+\.pdf$#', $doc['file']['url']);
            $source = self::root() . '/seed/files/demo/' . basename($doc['file']['url']);
            $this->assertFileExists($source);
            $this->assertSame(filesize($source), $doc['file']['bytes']);
        }
        foreach (['programma-vospitaniya-2026.pdf', 'kalendarnyj-plan-2026.pdf'] as $pdf) {
            $this->assertStringStartsWith('%PDF-', (string) file_get_contents(self::root() . '/seed/files/docs/' . $pdf, false, null, 0, 5));
        }
    }

    #[DataProvider('seedSets')]
    public function test_richtext_uses_allowed_tags_only(string $set): void
    {
        $html = [];
        $texts = self::read($set, 'texts');
        foreach (self::registry() as $key => $def) {
            if ($def['type'] === 'richtext') {
                $html[$key] = $texts[$key];
            }
        }
        foreach (Collections::all() as $name => $def) {
            foreach ($def['fields'] as $field) {
                if ($field['type'] !== 'richtext') {
                    continue;
                }
                foreach (self::read($set, $name)['items'] as $i => $item) {
                    $html["$name#$i.{$field['name']}"] = $item[$field['name']];
                }
            }
        }

        foreach ($html as $where => $value) {
            preg_match_all('#<\s*(/?)\s*([a-zA-Z0-9]+)([^>]*)>#', $value, $tags, PREG_SET_ORDER);
            foreach ($tags as [, $closing, $tag, $attrs]) {
                $this->assertContains($tag, self::RICH_TAGS, "$set $where: <$tag>");
                if ($closing === '' && $tag === 'a') {
                    $this->assertMatchesRegularExpression('#^\s+href="(https://|/|mailto:|tel:)[^"]+"( rel="noopener noreferrer")?\s*$#', $attrs, "$set $where");
                } else {
                    $this->assertSame('', trim($attrs), "$set $where: атрибуты у <$tag>");
                }
            }
            $this->assertSame(substr_count($value, '<p>'), substr_count($value, '</p>'), "$set $where: парные <p>");
        }
    }

    public function test_seed_missing_does_not_overwrite(): void
    {
        $store = $this->tmpStore();
        $store->write('settings', ['phone' => '+79990001122'], 'office');
        $before = $store->hash('settings');

        Seeder::seedMissing($store, self::root() . '/seed/data');

        $this->assertSame($before, $store->hash('settings'));
        $this->assertSame([], $store->versions('settings'));
        $this->assertSame(self::read('data', 'texts'), $store->read('texts'), 'недостающие файлы появились');
        $this->assertSame(self::read('data', 'team'), $store->read('team'));
    }

    public function test_seed_overwrite_replaces_and_keeps_version(): void
    {
        $store = $this->tmpStore();
        $store->write('settings', ['phone' => '+79990001122'], 'office');

        $written = Seeder::seed($store, self::root() . '/seed/demo', true);

        $this->assertContains('settings', $written);
        $this->assertSame(self::read('demo', 'settings'), $store->read('settings'));
        $this->assertCount(1, $store->versions('settings'), 'прежние данные остались в истории');
    }

    public function test_copy_missing_files_keeps_existing(): void
    {
        $from = $this->tmpDir();
        $to = $this->tmpDir() . '/uploads/docs';
        file_put_contents($from . '/a.pdf', 'new-a');
        file_put_contents($from . '/b.pdf', 'new-b');
        mkdir($to, 0777, true);
        file_put_contents($to . '/a.pdf', 'old-a');

        $copied = Seeder::copyMissingFiles($from, $to);

        $this->assertSame(['b.pdf'], $copied);
        $this->assertSame('old-a', file_get_contents($to . '/a.pdf'));
        $this->assertSame('new-b', file_get_contents($to . '/b.pdf'));
    }

    public function test_app_boot_seeds_empty_storage(): void
    {
        $storage = $this->tmpDir();
        $config = array_replace_recursive(require self::root() . '/config/config.php', [
            'env' => 'test',
            'storageDir' => $storage,
        ]);

        App::boot($config);

        $this->assertFileExists($storage . '/data/settings.json');
        $this->assertSame(self::read('data', 'faq'), json_decode((string) file_get_contents($storage . '/data/faq.json'), true));
    }

    public function test_seed_script_seeds_target(): void
    {
        $target = $this->tmpDir();

        [$code] = self::runSeedScript(['--target=' . $target], ['GROZA_ENV' => 'dev']);

        $this->assertSame(0, $code);
        $this->assertSame(self::read('data', 'settings'), json_decode((string) file_get_contents($target . '/data/settings.json'), true));
    }

    public function test_seed_script_refuses_force_in_prod(): void
    {
        $target = $this->tmpDir();

        [$code, $output] = self::runSeedScript(['--force', '--target=' . $target], ['GROZA_ENV' => 'prod']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('--force', $output);
        $this->assertDirectoryDoesNotExist($target . '/data');
    }

    // --- helpers

    private static function root(): string
    {
        return dirname(__DIR__, 4);
    }

    /** @return array<string,mixed> */
    private static function read(string $set, string $name): array
    {
        $file = self::root() . "/seed/$set/$name.json";
        self::assertFileExists($file);

        return json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string,array<string,string>> */
    private static function registry(): array
    {
        return require self::root() . '/config/texts.php';
    }

    private function tmpDir(): string
    {
        $dir = sys_get_temp_dir() . '/groza-seed-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        $this->tmpDirs[] = $dir;

        return $dir;
    }

    private function tmpStore(): JsonStore
    {
        $dir = $this->tmpDir();

        return new JsonStore($dir . '/data', $dir . '/versions');
    }

    /**
     * @param list<string> $args
     * @param array<string,string> $env
     * @return array{int,string}
     */
    private static function runSeedScript(array $args, array $env): array
    {
        // путь к скрипту — относительный: в Windows кириллица в аргументах дочернего процесса портится
        $cmd = array_merge([PHP_BINARY, 'scripts/seed.php'], $args);
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, self::root(), array_merge(getenv(), $env));
        self::assertIsResource($proc);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($proc), (string) $output];
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
