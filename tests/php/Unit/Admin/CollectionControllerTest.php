<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Admin;

use Groza\Admin\FormRenderer;
use Groza\Admin\ImageProcessor;
use Groza\Admin\Uploads;
use Groza\App;
use Groza\Domain\Collections;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Support\Csrf;
use Groza\Support\Session;
use Groza\Tests\Support\AdminHttp;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

final class CollectionControllerTest extends TestCase
{
    private App $app;
    private string $uploadsDir;

    protected function setUp(): void
    {
        Session::reset();
        $this->app = TestApp::make();
        $this->uploadsDir = sys_get_temp_dir() . '/groza-cc-up-' . bin2hex(random_bytes(6));
        mkdir($this->uploadsDir, 0777, true);
        // Тестовые «загрузки»: файл не приходил по HTTP — подменяем проверку и перенос (фикстура остаётся на месте)
        $this->app->setUploads(new Uploads(
            $this->uploadsDir,
            '/uploads',
            new ImageProcessor(),
            10485760,
            20971520,
            static fn (string $f): bool => true,
            static fn (string $a, string $b): bool => copy($a, $b)
        ));
        AdminHttp::user($this->app);
        AdminHttp::login($this->app);
    }

    protected function tearDown(): void
    {
        Session::reset();
        $this->rm($this->uploadsDir);
    }

    private function rm(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $i) {
            $i->isDir() ? rmdir($i->getPathname()) : unlink($i->getPathname());
        }
        rmdir($dir);
    }

    /** @return list<array<string,mixed>> */
    private function items(string $name = 'shifts'): array
    {
        return $this->app->store()->read($name)['items'];
    }

    /** @return array<string,mixed> */
    private function shift(string $id): array
    {
        foreach ($this->items() as $s) {
            if ($s['id'] === $id) {
                return $s;
            }
        }
        $this->fail("нет смены $id");
    }

    private function hash(string $name = 'shifts'): string
    {
        return $this->app->store()->hash($name);
    }

    /** @return array<string,mixed> */
    private function valid(array $over = []): array
    {
        return $over + [
            '_hash' => $this->hash(),
            'title' => 'Новая смена',
            'dateStart' => '2027-08-01',
            'dateEnd' => '2027-08-10',
            'ageMin' => '8',
            'ageMax' => '14',
            'price' => '45 000',
            'status' => 'open',
        ];
    }

    /** @param array<string,mixed> $files */
    private function save(string $id, array $post, array $files = [], string $collection = 'shifts'): Response
    {
        return $this->app->handle(Request::create('POST', "/admin/$collection/$id/", [], ['_csrf' => Csrf::token()] + $post, [], $files));
    }

    private function auditActions(): array
    {
        return array_column($this->app->audit()->recent(50), 'action');
    }

    private function upload(string $fixture, string $name): array
    {
        $src = dirname(__DIR__, 3) . '/fixtures/uploads/' . $fixture;

        return ['name' => $name, 'type' => '', 'tmp_name' => $src, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($src)];
    }

    private function noFile(): array
    {
        return ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0];
    }

    private function uploadedFiles(): array
    {
        return glob($this->uploadsDir . '/*/*') ?: [];
    }

    // ---------- создание и правка ----------

    public function test_create_shift(): void
    {
        $before = count($this->items());
        $res = $this->save('new', $this->valid());

        $this->assertSame(303, $res->status());
        $items = $this->items();
        $this->assertCount($before + 1, $items);
        $new = end($items);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{12}$/', $new['id']);
        $this->assertSame('Новая смена', $new['title']);
        $this->assertSame(45000, $new['price']);
        $this->assertSame(10, $new['days']);
        $this->assertSame('olga', $new['updatedBy']);
        $this->assertNotEmpty($new['updatedAt']);
        $this->assertSame('/admin/shifts/' . $new['id'] . '/?saved=1&created=1', $res->header('Location'));
        $this->assertContains('shifts.create', $this->auditActions());
        $this->assertSame($new['id'], $this->app->audit()->recent(1)[0]['target']);
        $this->assertSame('olga', $this->app->audit()->recent(1)[0]['actor']);
    }

    public function test_update_shift_keeps_id_and_position(): void
    {
        $first = $this->items()[1];
        $res = $this->save($first['id'], $this->valid(['title' => 'Переименована', 'status' => 'few']));

        $this->assertSame(303, $res->status());
        $this->assertSame($first['id'], $this->items()[1]['id']);
        $this->assertSame('Переименована', $this->items()[1]['title']);
        $this->assertSame('few', $this->items()[1]['status']);
        $this->assertContains('shifts.update', $this->auditActions());
    }

    public function test_saved_page_shows_flash_and_link_to_site(): void
    {
        $this->save('new', $this->valid());
        $all = $this->items();
        $new = end($all);
        $res = AdminHttp::get($this->app, '/admin/shifts/' . $new['id'] . '/', ['saved' => '1']);

        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('Сохранено', $res->body());
        $this->assertStringContainsString('Посмотреть на сайте', $res->body());
        $this->assertStringContainsString('href="/program/' . $new['slug'] . '/"', $res->body());
        $this->assertStringContainsString('Где на сайте', $res->body());
        $without = AdminHttp::get($this->app, '/admin/shifts/' . $new['id'] . '/');
        $this->assertStringNotContainsString('alert-ok', $without->body());
    }

    public function test_duplicate_slug_is_rejected(): void
    {
        $existing = $this->items()[0];
        $res = $this->save('new', $this->valid(['slug' => $existing['slug']]));

        $this->assertSame(422, $res->status());
        $this->assertStringContainsString('Такой адрес уже занят', $res->body());
    }

    public function test_invalid_rerenders_422_with_values(): void
    {
        $hash = $this->hash();
        $res = $this->save('new', $this->valid(['title' => '', 'ageMin' => 'abc', 'price' => '12 345', 'body' => 'Длинный текст описания']));

        $this->assertSame(422, $res->status());
        $body = $res->body();
        $this->assertStringContainsString('Заполните поле', $body);
        $this->assertStringContainsString('Введите целое число', $body);
        $this->assertStringContainsString('value="12 345"', $body, 'введённое сохраняется');
        $this->assertStringContainsString('Длинный текст описания', $body);
        $this->assertStringContainsString('value="abc"', $body);
        $this->assertStringContainsString('href="#f-title"', $body, 'сводка ошибок со ссылками на поля');
        $this->assertStringContainsString('aria-invalid="true"', $body);
        $this->assertSame($hash, $this->hash(), 'файл не тронут');
    }

    public function test_conflict_409_keeps_input(): void
    {
        $staleHash = $this->hash();
        // «Кто-то другой» сохранил смену
        $this->assertSame(303, $this->save('new', $this->valid(['title' => 'Чужая смена', '_hash' => $staleHash]))->status());
        $afterOther = $this->hash();
        $before = file_get_contents($this->app->config()['storageDir'] . '/data/shifts.json');

        $res = $this->save('new', $this->valid(['title' => 'Моя смена', 'body' => 'Мой длинный текст', '_hash' => $staleHash]));

        $this->assertSame(409, $res->status());
        $this->assertStringContainsString('Пока вы редактировали, кто-то сохранил изменения', $res->body());
        $this->assertStringContainsString('Моя смена', $res->body());
        $this->assertStringContainsString('Мой длинный текст', $res->body());
        $this->assertSame($before, file_get_contents($this->app->config()['storageDir'] . '/data/shifts.json'), 'файл не изменился');
        $this->assertSame($afterOther, $this->hash());
        $this->assertStringContainsString('name="_hash" value="' . $afterOther . '"', $res->body(), 'свежий _hash');
        $this->assertCount(1, array_filter($this->items(), fn ($s) => $s['title'] === 'Чужая смена'));
        $this->assertCount(0, array_filter($this->items(), fn ($s) => $s['title'] === 'Моя смена'));

        // Второе нажатие «Сохранить» со свежим _hash проходит осознанно
        $again = $this->save('new', $this->valid(['title' => 'Моя смена', '_hash' => $afterOther]));
        $this->assertSame(303, $again->status());
        $this->assertCount(1, array_filter($this->items(), fn ($s) => $s['title'] === 'Моя смена'));
    }

    public function test_missing_hash_is_a_conflict(): void
    {
        $post = $this->valid();
        unset($post['_hash']);
        $this->assertSame(409, $this->save('new', $post)->status());
    }

    // ---------- состояние, порядок, удаление ----------

    public function test_status_toggle(): void
    {
        $s = $this->items()[0];
        $this->assertNotSame('closed', $s['status']);
        $res = AdminHttp::postWithToken($this->app, '/admin/shifts/' . $s['id'] . '/status/', ['status' => 'closed']);

        $this->assertSame(303, $res->status());
        $this->assertSame('closed', $this->shift($s['id'])['status']);
        $this->assertSame('olga', $this->shift($s['id'])['updatedBy']);
        $this->assertSame('shifts.status', $this->app->audit()->recent(1)[0]['action']);
        $this->assertSame($s['id'], $this->app->audit()->recent(1)[0]['target']);
        $booking = TestApp::get($this->app, '/booking/');
        $this->assertStringContainsString('Мест нет', $booking->body());
    }

    public function test_status_rejects_unknown_value_and_missing_csrf(): void
    {
        $s = $this->items()[0];
        $hash = $this->hash();
        $this->assertSame(422, AdminHttp::postWithToken($this->app, '/admin/shifts/' . $s['id'] . '/status/', ['status' => 'bogus'])->status());
        $this->assertSame(419, AdminHttp::post($this->app, '/admin/shifts/' . $s['id'] . '/status/', ['status' => 'closed'])->status());
        $this->assertSame(404, AdminHttp::postWithToken($this->app, '/admin/shifts/aaaaaaaaaaaa/status/', ['status' => 'closed'])->status());
        $this->assertSame($hash, $this->hash());
    }

    public function test_move_up_swaps_order(): void
    {
        $ids = array_column($this->items('venues'), 'id');
        $res = AdminHttp::postWithToken($this->app, '/admin/venues/' . $ids[1] . '/move/', ['dir' => 'up']);

        $this->assertSame(303, $res->status());
        $this->assertSame([$ids[1], $ids[0]], array_column($this->items('venues'), 'id'));
        $this->assertSame('venues.move', $this->app->audit()->recent(1)[0]['action']);

        AdminHttp::postWithToken($this->app, '/admin/venues/' . $ids[1] . '/move/', ['dir' => 'down']);
        $this->assertSame($ids, array_column($this->items('venues'), 'id'));
    }

    public function test_move_at_the_edge_changes_nothing(): void
    {
        $hash = $this->hash('venues');
        $first = $this->items('venues')[0]['id'];
        $this->assertSame(303, AdminHttp::postWithToken($this->app, "/admin/venues/$first/move/", ['dir' => 'up'])->status());
        $this->assertSame($hash, $this->hash('venues'));
        $this->assertSame(422, AdminHttp::postWithToken($this->app, "/admin/venues/$first/move/", ['dir' => 'sideways'])->status());
    }

    public function test_shifts_are_not_orderable(): void
    {
        $hash = $this->hash();
        $first = $this->items()[0]['id'];
        $this->assertSame(422, AdminHttp::postWithToken($this->app, "/admin/shifts/$first/move/", ['dir' => 'down'])->status());
        $this->assertSame($hash, $this->hash());
        $this->assertStringNotContainsString('name="dir"', AdminHttp::get($this->app, '/admin/shifts/')->body());
        $this->assertStringContainsString('name="dir"', AdminHttp::get($this->app, '/admin/venues/')->body());
    }

    public function test_move_renumbers_order_field(): void
    {
        $faq = $this->items('faq');
        usort($faq, fn ($a, $b) => $a['order'] <=> $b['order']);
        $second = $faq[1]['id'];
        AdminHttp::postWithToken($this->app, "/admin/faq/$second/move/", ['dir' => 'up']);

        $after = $this->items('faq');
        usort($after, fn ($a, $b) => $a['order'] <=> $b['order']);
        $this->assertSame($second, $after[0]['id']);
        $this->assertSame($faq[0]['id'], $after[1]['id']);
        $this->assertSame([10, 20, 30], array_slice(array_column($after, 'order'), 0, 3));
    }

    public function test_delete_requires_post_and_csrf(): void
    {
        $s = $this->items()[0];
        $count = count($this->items());

        // GET — только страница подтверждения, ничего не удаляется
        $confirm = AdminHttp::get($this->app, '/admin/shifts/' . $s['id'] . '/delete/');
        $this->assertSame(200, $confirm->status());
        $this->assertStringContainsString($s['title'], $confirm->body());
        $this->assertCount($count, $this->items());

        $this->assertSame(419, AdminHttp::post($this->app, '/admin/shifts/' . $s['id'] . '/delete/', ['confirm' => '1'])->status());
        $this->assertCount($count, $this->items());

        // без подтверждения — тоже не удаляет
        $noConfirm = AdminHttp::postWithToken($this->app, '/admin/shifts/' . $s['id'] . '/delete/', []);
        $this->assertSame(200, $noConfirm->status());
        $this->assertCount($count, $this->items());

        $ok = AdminHttp::postWithToken($this->app, '/admin/shifts/' . $s['id'] . '/delete/', ['confirm' => '1']);
        $this->assertSame(303, $ok->status());
        $this->assertCount($count - 1, $this->items());
        $this->assertSame('shifts.delete', $this->app->audit()->recent(1)[0]['action']);
        $this->assertSame($s['id'], $this->app->audit()->recent(1)[0]['target']);
    }

    // ---------- адреса ----------

    public function test_unknown_collection_404(): void
    {
        foreach (['/admin/nope/', '/admin/nope/new/', '/admin/nope/aaaaaaaaaaaa/', '/admin/nope/aaaaaaaaaaaa/delete/'] as $path) {
            $res = AdminHttp::get($this->app, $path);
            $this->assertSame(404, $res->status(), $path);
            $this->assertStringContainsString('Админка', $res->body());
        }
        $this->assertSame(404, AdminHttp::postWithToken($this->app, '/admin/nope/new/', ['title' => 'x'])->status());
        $this->assertSame(404, AdminHttp::postWithToken($this->app, '/admin/nope/aaaaaaaaaaaa/move/', ['dir' => 'up'])->status());
    }

    public function test_bad_or_missing_id_404(): void
    {
        $this->assertSame(404, AdminHttp::get($this->app, '/admin/shifts/zzzzzzzzzzzz/')->status());
        $this->assertSame(404, AdminHttp::get($this->app, '/admin/shifts/aaaaaaaaaaaa/')->status());
        $this->assertSame(404, AdminHttp::get($this->app, '/admin/shifts/aaaaaaaaaaaa/delete/')->status());
        $this->assertSame(404, $this->save('not-an-id', $this->valid())->status());
    }

    public function test_admin_pages_are_private_and_need_login(): void
    {
        $res = AdminHttp::get($this->app, '/admin/shifts/');
        $this->assertStringContainsString('no-store', (string) $res->header('Cache-Control'));
        $this->assertSame('noindex, nofollow', $res->header('X-Robots-Tag'));

        Session::reset();
        $anon = AdminHttp::get($this->app, '/admin/shifts/');
        $this->assertSame(302, $anon->status());
        $this->assertStringContainsString('/admin/login/', (string) $anon->header('Location'));
    }

    public function test_auth_routes_not_shadowed_by_collections(): void
    {
        $this->assertSame(200, AdminHttp::get($this->app, '/admin/password/')->status());
    }

    public function test_dashboard_lists_sections(): void
    {
        $res = AdminHttp::get($this->app, '/admin/');
        $this->assertSame(200, $res->status());
        foreach (Collections::all() as $name => $def) {
            $this->assertStringContainsString('href="/admin/' . $name . '/"', $res->body());
            $this->assertStringContainsString($def['label'], $res->body());
        }
        $this->assertStringContainsString('Здравствуйте, Ольга', $res->body());
    }

    // ---------- список ----------

    public function test_shifts_list_has_status_buttons_with_pressed_state(): void
    {
        $res = AdminHttp::get($this->app, '/admin/shifts/');
        $this->assertSame(200, $res->status());
        $body = $res->body();
        $s = $this->items()[0];
        $this->assertStringContainsString('action="/admin/shifts/' . $s['id'] . '/status/"', $body);
        foreach (['Скоро', 'Идёт набор', 'Мало мест', 'Мест нет'] as $label) {
            $this->assertStringContainsString($label, $body);
        }
        $this->assertSame(1, preg_match_all('#value="' . $s['status'] . '"[^>]*aria-pressed="true"#', $body));
        $this->assertStringContainsString('href="/admin/shifts/new/"', $body);
        $this->assertStringContainsString('href="/admin/shifts/' . $s['id'] . '/"', $body);
        $this->assertStringContainsString('/delete/', $body);
        $this->assertStringContainsString('name="_csrf"', $body);
    }

    public function test_generic_list_works_for_other_collections(): void
    {
        foreach (['venues', 'team', 'documents', 'vacancies', 'faq', 'reviews'] as $name) {
            $res = AdminHttp::get($this->app, "/admin/$name/");
            $this->assertSame(200, $res->status(), $name);
            $this->assertStringNotContainsString('/status/', $res->body(), $name);
            $edit = AdminHttp::get($this->app, "/admin/$name/new/");
            $this->assertSame(200, $edit->status(), $name);
        }
    }

    // ---------- форма ----------

    public function test_edit_form_is_built_from_definition(): void
    {
        $s = $this->items()[0];
        $body = AdminHttp::get($this->app, '/admin/shifts/' . $s['id'] . '/')->body();

        $this->assertStringContainsString('name="_hash" value="' . $this->hash() . '"', $body);
        $this->assertStringContainsString('name="_csrf"', $body);
        $this->assertStringContainsString('data-draft', $body);
        $this->assertStringContainsString('enctype="multipart/form-data"', $body);
        $this->assertStringContainsString('<label for="f-title">Название', $body);
        $this->assertStringContainsString('maxlength="120"', $body);
        $this->assertStringContainsString('type="date"', $body);
        $this->assertStringContainsString('value="' . $s['dateStart'] . '"', $body);
        $this->assertMatchesRegularExpression('#type="radio" name="status" value="open"#', $body, 'enum из 4 вариантов — радиокнопки');
        $this->assertStringContainsString('role="switch"', $body, 'bool — переключатель');
        $this->assertStringContainsString('Если оставить пустым, возьмётся из названия', $body, 'подсказка');
        $this->assertStringContainsString('<select', $body, 'ref — выпадающий список');
        $this->assertStringContainsString('ДЗОЛ «Солнышко»', $body, 'подпись ссылки — название записи');
        $this->assertMatchesRegularExpression('#<textarea[^>]*name="activities"#', $body);
        $this->assertMatchesRegularExpression('#<textarea[^>]*name="body"#', $body);
        $this->assertStringContainsString('data-count-for="f-title"', $body, 'счётчик знаков');
        $this->assertStringContainsString('aria-required="true"', $body);
        $this->assertStringContainsString('Посмотреть на сайте', $body);
        $this->assertStringContainsString('href="/program/' . $s['slug'] . '/"', $body);
        // вычисляемое поле — только для чтения, без ввода
        $this->assertStringNotContainsString('name="days"', $body);
        $this->assertStringContainsString('Считается по датам', $body);
        $this->assertStringContainsString('/assets/js/admin.js', $body);
    }

    public function test_form_renderer_escapes_values(): void
    {
        $def = Collections::get('shifts');
        $renderer = new FormRenderer($this->app->view(), ['venues' => ['x' => '<b>База</b>']]);
        $html = $renderer->render($def, ['title' => '"><script>alert(1)</script>', 'venue' => 'x', 'body' => '</textarea><script>1</script>'], ['title' => '<i>err</i>']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>База', $html);
        $this->assertStringNotContainsString('<i>err', $html);
        $this->assertStringContainsString('&lt;i&gt;err', $html);
    }

    public function test_form_renderer_list_and_select_values(): void
    {
        $def = Collections::get('shifts');
        $renderer = new FormRenderer($this->app->view(), ['venues' => ['a' => 'Первая', 'b' => 'Вторая']]);
        $html = $renderer->render($def, ['activities' => ['Один', 'Два'], 'venue' => 'b', 'hidden' => true, 'status' => 'few'], []);

        $this->assertStringContainsString("Один\nДва", $html);
        $this->assertMatchesRegularExpression('#<option value="b" selected>Вторая#', $html);
        $this->assertMatchesRegularExpression('#name="hidden"[^>]*checked#', $html);
        $this->assertMatchesRegularExpression('#value="few"[^>]*checked#', $html);
    }

    public function test_list_field_round_trip_one_item_per_line(): void
    {
        $s = $this->items()[0];
        $this->save($s['id'], $this->valid(['activities' => "Первое\r\n\r\nВторое\n  Третье  "]));
        $this->assertSame(['Первое', 'Второе', 'Третье'], $this->shift($s['id'])['activities']);
    }

    public function test_bool_switch_saves_hidden_flag(): void
    {
        $s = $this->items()[0];
        $this->save($s['id'], $this->valid(['hidden' => '1']));
        $this->assertTrue($this->shift($s['id'])['hidden']);
        $this->save($s['id'], $this->valid());
        $this->assertFalse($this->shift($s['id'])['hidden']);
    }

    public function test_admin_templates_have_no_inline_script_or_style(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 4) . '/templates/admin', \FilesystemIterator::SKIP_DOTS));
        $n = 0;
        foreach ($files as $f) {
            $src = (string) file_get_contents($f->getPathname());
            $n++;
            $this->assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $src, $f->getPathname());
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\ssrc=)/i', $src, $f->getPathname());
            $this->assertDoesNotMatchRegularExpression('/\son(click|change|submit|load|input)\s*=/i', $src, $f->getPathname());
        }
        $this->assertGreaterThan(10, $n);
    }

    // ---------- загрузки (ruling 1) ----------

    public function test_client_supplied_descriptor_is_ignored(): void
    {
        $s = $this->items()[0];
        $stored = $s['photo'];
        $evil = ['src' => '/uploads/img/../../../etc/passwd', 'widths' => [1], 'formats' => ['jpg'], 'w' => 1, 'h' => 1];
        $this->save($s['id'], $this->valid(['photo' => $evil, 'photo__action' => 'keep']));
        $this->assertSame($stored, $this->shift($s['id'])['photo']);

        $this->save($s['id'], $this->valid(['photo' => json_encode($evil), 'venue' => 'solnyshko']));
        $this->assertSame($stored, $this->shift($s['id'])['photo']);

        $this->save($s['id'], $this->valid(['photo' => 'some-other-key']));
        $this->assertSame($stored, $this->shift($s['id'])['photo']);
    }

    public function test_photo_remove_choice_clears_it(): void
    {
        $s = $this->items()[0];
        $this->save($s['id'], $this->valid(['photo__action' => 'remove']));
        $this->assertNull($this->shift($s['id'])['photo']);
    }

    public function test_upload_stores_server_made_descriptor(): void
    {
        $s = $this->items()[0];
        $res = $this->save($s['id'], $this->valid(['photo' => 'client-says-this']), ['photo' => $this->upload('small.jpg', 'IMG_1.JPG')]);

        $this->assertSame(303, $res->status());
        $photo = $this->shift($s['id'])['photo'];
        $this->assertIsArray($photo);
        $this->assertMatchesRegularExpression('#^/uploads/img/[0-9a-f]{16}$#', $photo['src']);
        $this->assertNotEmpty($this->uploadedFiles());
    }

    public function test_upload_happens_only_after_validation_passes(): void
    {
        $s = $this->items()[0];
        $hash = $this->hash();
        $res = $this->save($s['id'], $this->valid(['title' => '']), ['photo' => $this->upload('small.jpg', 'a.jpg')]);

        $this->assertSame(422, $res->status());
        $this->assertSame([], $this->uploadedFiles(), 'ничего не загружено, пока остальные поля неверны');
        $this->assertSame($hash, $this->hash());
        $this->assertStringContainsString('Выберите файл снова', $res->body());
    }

    public function test_upload_error_is_shown_as_field_error(): void
    {
        $s = $this->items()[0];
        $hash = $this->hash();
        $res = $this->save($s['id'], $this->valid(), ['photo' => $this->upload('fake.jpg', 'a.jpg')]);

        $this->assertSame(422, $res->status());
        $this->assertStringContainsString('Файл не похож на фото', $res->body());
        $this->assertSame($hash, $this->hash());
        $this->assertSame([], $this->uploadedFiles());
    }

    public function test_empty_file_input_is_not_an_upload(): void
    {
        $s = $this->items()[0];
        $stored = $s['photo'];
        $res = $this->save($s['id'], $this->valid(), ['photo' => $this->noFile()]);
        $this->assertSame(303, $res->status());
        $this->assertSame($stored, $this->shift($s['id'])['photo']);
    }

    public function test_write_time_conflict_deletes_just_uploaded_file(): void
    {
        // Гонка: хеш при проверке верный, но пока файл принимается, кто-то сохраняет документы
        $app = $this->app;
        $app->setUploads(new Uploads(
            $this->uploadsDir,
            '/uploads',
            new ImageProcessor(),
            10485760,
            20971520,
            static fn (string $f): bool => true,
            static function (string $a, string $b) use ($app): bool {
                $data = $app->store()->read('documents');
                $data['items'][1]['title'] = 'Чужая правка в момент загрузки';
                $app->store()->write('documents', $data, 'other');

                return copy($a, $b);
            }
        ));
        $doc = $this->items('documents')[0];
        $other = $this->items('documents')[1]['title'];
        $post = ['_hash' => $this->hash('documents'), 'title' => $doc['title'], 'section' => $doc['section'], 'order' => '10'];

        $res = $this->save($doc['id'], $post, ['file' => $this->upload('real.pdf', 'Устав.pdf')], 'documents');

        $this->assertSame(409, $res->status());
        $this->assertStringContainsString('Пока вы редактировали, кто-то сохранил изменения', $res->body());
        $this->assertSame([], $this->uploadedFiles(), 'только что загруженный файл удалён');
        $this->assertSame('Чужая правка в момент загрузки', $this->items('documents')[1]['title']);
        $this->assertNotSame($other, $this->items('documents')[1]['title']);
        $this->assertSame($doc['file'], $this->items('documents')[0]['file']);
    }

    public function test_422_keeps_submitted_hash_so_interleaved_change_gives_409(): void
    {
        $s = $this->items()[0];
        $h0 = $this->hash();
        $res = $this->save($s['id'], $this->valid(['title' => '', '_hash' => $h0]));
        $this->assertSame(422, $res->status());

        // пока форма с ошибкой открыта, с телефона переключили статус другой смены
        $other = $this->items()[1]['id'];
        AdminHttp::postWithToken($this->app, "/admin/shifts/$other/status/", ['status' => 'closed']);
        $this->assertNotSame($h0, $this->hash());
        $this->assertStringContainsString('name="_hash" value="' . $h0 . '"', $res->body(), 'в форме с ошибкой — прежний _hash');

        $again = $this->save($s['id'], $this->valid(['_hash' => $h0]));
        $this->assertSame(409, $again->status());
        $this->assertSame('closed', $this->shift($other)['status'], 'чужое переключение не затёрто');
        $this->assertStringContainsString('name="_hash" value="' . $this->hash() . '"', $again->body(), 'после 409 — свежий');
    }

    public function test_record_deleted_while_editing_shows_values_not_bare_404(): void
    {
        $s = $this->items()[0];
        AdminHttp::postWithToken($this->app, '/admin/shifts/' . $s['id'] . '/delete/', ['confirm' => '1']);
        $res = $this->save($s['id'], $this->valid(['title' => 'Моя правка', 'body' => 'Мой длинный текст']));

        $this->assertSame(409, $res->status());
        $this->assertStringContainsString('Эту запись удалили, пока вы её редактировали', $res->body());
        $this->assertStringContainsString('Моя правка', $res->body());
        $this->assertStringContainsString('Мой длинный текст', $res->body());
        $this->assertStringContainsString('action="/admin/shifts/new/"', $res->body());
        $this->assertStringContainsString('data-draft-unsaved', $res->body());
    }

    public function test_unsaved_marker_only_on_rerendered_forms(): void
    {
        $s = $this->items()[0];
        $this->assertStringNotContainsString('data-draft-unsaved', AdminHttp::get($this->app, '/admin/shifts/' . $s['id'] . '/')->body());
        $this->assertStringContainsString('data-draft-unsaved', $this->save($s['id'], $this->valid(['title' => '']))->body());
        $this->assertStringContainsString('data-draft-unsaved', $this->save($s['id'], $this->valid(['_hash' => 'stale']))->body());
    }

    public function test_old_file_deleted_only_after_successful_save(): void
    {
        $s = $this->items()[0];
        // Сначала загружаем первое фото
        $this->save($s['id'], $this->valid(), ['photo' => $this->upload('small.jpg', 'a.jpg')]);
        $old = $this->shift($s['id'])['photo']['src'];
        $oldFiles = $this->uploadedFiles();
        $this->assertNotEmpty($oldFiles);

        // Неудачное сохранение (422) со вторым фото: старое остаётся, нового нет
        $this->save($s['id'], $this->valid(['title' => '']), ['photo' => $this->upload('upright.jpg', 'b.jpg')]);
        $this->assertSame($oldFiles, $this->uploadedFiles());

        // Конфликт: старое остаётся, новое удалено
        $this->save($s['id'], $this->valid(['_hash' => 'stale']), ['photo' => $this->upload('upright.jpg', 'b.jpg')]);
        $this->assertSame($oldFiles, $this->uploadedFiles());
        $this->assertSame($old, $this->shift($s['id'])['photo']['src']);

        // Успех: старое удалено, новое на месте
        $this->assertSame(303, $this->save($s['id'], $this->valid(), ['photo' => $this->upload('upright.jpg', 'b.jpg')])->status());
        $new = $this->shift($s['id'])['photo']['src'];
        $this->assertNotSame($old, $new);
        foreach ($oldFiles as $f) {
            $this->assertFileDoesNotExist($f);
        }
        $this->assertNotEmpty($this->uploadedFiles());
    }

    public function test_removing_and_deleting_record_removes_its_files(): void
    {
        $s = $this->items()[0];
        $this->save($s['id'], $this->valid(), ['photo' => $this->upload('small.jpg', 'a.jpg')]);
        $this->assertNotEmpty($this->uploadedFiles());

        $this->save($s['id'], $this->valid(['photo__action' => 'remove']));
        $this->assertSame([], $this->uploadedFiles());

        $this->save($s['id'], $this->valid(), ['photo' => $this->upload('small.jpg', 'a.jpg')]);
        AdminHttp::postWithToken($this->app, '/admin/shifts/' . $s['id'] . '/delete/', ['confirm' => '1']);
        $this->assertSame([], $this->uploadedFiles());
    }

    public function test_pdf_field_upload_and_required(): void
    {
        $doc = $this->items('documents')[0];
        $post = ['_hash' => $this->hash('documents'), 'title' => $doc['title'], 'section' => $doc['section'], 'order' => '10'];

        $keep = $this->save($doc['id'], $post, [], 'documents');
        $this->assertSame(303, $keep->status());
        $this->assertSame($doc['file'], $this->items('documents')[0]['file']);

        $post['_hash'] = $this->hash('documents');
        $res = $this->save($doc['id'], $post + ['file__action' => 'remove'], [], 'documents');
        $this->assertSame(422, $res->status(), 'файл обязателен');

        $post['_hash'] = $this->hash('documents');
        $up = $this->save($doc['id'], $post, ['file' => $this->upload('real.pdf', 'Устав.pdf')], 'documents');
        $this->assertSame(303, $up->status());
        $file = $this->items('documents')[0]['file'];
        $this->assertMatchesRegularExpression('#^/uploads/docs/[0-9a-f]{16}\.pdf$#', $file['url']);
        $this->assertSame('Устав.pdf', $file['originalName']);
    }
}
