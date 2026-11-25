<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Admin;

use Groza\Admin\ImageProcessor;
use Groza\Admin\Uploads;
use Groza\App;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Support\Csrf;
use Groza\Support\Session;
use Groza\Tests\Support\AdminHttp;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/** Редакторы баз, команды, документов, вакансий, вопросов и отзывов: фото с подписью, PDF, галерея, скрытие. */
final class CollectionFilesTest extends TestCase
{
    private const ALT_MESSAGE = 'Опишите, что на фото, — это нужно незрячим посетителям';

    private App $app;
    private string $uploadsDir;

    protected function setUp(): void
    {
        Session::reset();
        $this->app = TestApp::make();
        $this->uploadsDir = sys_get_temp_dir() . '/groza-cf-up-' . bin2hex(random_bytes(6));
        mkdir($this->uploadsDir, 0777, true);
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
    private function items(string $name): array
    {
        return $this->app->store()->read($name)['items'];
    }

    private function hash(string $name): string
    {
        return $this->app->store()->hash($name);
    }

    /** @param array<string,mixed> $post @param array<string,mixed> $files */
    private function save(string $collection, string $id, array $post, array $files = []): Response
    {
        return $this->app->handle(Request::create('POST', "/admin/$collection/$id/", [], ['_csrf' => Csrf::token(), '_hash' => $this->hash($collection)] + $post, [], $files));
    }

    /** @return array<string,mixed> */
    private function upload(string $fixture, string $name): array
    {
        $src = dirname(__DIR__, 3) . '/fixtures/uploads/' . $fixture;

        return ['name' => $name, 'type' => '', 'tmp_name' => $src, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($src)];
    }

    /** @return list<string> */
    private function uploadedFiles(): array
    {
        return glob($this->uploadsDir . '/*/*') ?: [];
    }

    /** @return array<string,mixed> */
    private function doc(array $over = []): array
    {
        return $over + ['title' => 'Устав лагеря', 'section' => 'uchreditelnye', 'date' => '2026-05-01', 'order' => '10'];
    }

    private function member(array $over = []): array
    {
        return $over + ['name' => 'Тестова Ольга Ивановна', 'role' => 'Педагог-организатор', 'group' => 'pedagogue', 'education' => 'Высшее', 'specialty' => 'Педагогика', 'qualification' => 'Педагог'];
    }

    private function plain(string $html): string
    {
        return trim((string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    // ---------- PDF ----------

    public function test_upload_document_pdf(): void
    {
        $res = $this->save('documents', 'new', $this->doc(), ['file' => $this->upload('real.pdf', 'Устав.pdf')]);

        $this->assertSame(303, $res->status());
        $saved = array_values(array_filter($this->items('documents'), static fn (array $d): bool => $d['title'] === 'Устав лагеря'))[0];
        $this->assertMatchesRegularExpression('#^/uploads/docs/[0-9a-f]{16}\.pdf$#', $saved['file']['url']);
        $disk = $this->uploadsDir . '/docs/' . basename($saved['file']['url']);
        $this->assertFileExists($disk);
        $this->assertSame(filesize($disk), $saved['file']['bytes'], 'размер записан');
        $this->assertGreaterThan(0, $saved['file']['bytes']);

        $page = TestApp::get($this->app, '/svedeniya/ob-organizacii/')->body();
        $this->assertStringContainsString('Устав лагеря', $page);
        $this->assertStringContainsString('PDF,', $page);
    }

    public function test_replace_file_deletes_old(): void
    {
        $this->save('documents', 'new', $this->doc(), ['file' => $this->upload('real.pdf', 'Устав.pdf')]);
        $doc = array_values(array_filter($this->items('documents'), static fn (array $d): bool => $d['title'] === 'Устав лагеря'))[0];
        $old = $this->uploadsDir . '/docs/' . basename($doc['file']['url']);
        $this->assertFileExists($old);

        $this->assertSame(303, $this->save('documents', $doc['id'], $this->doc(), ['file' => $this->upload('real.pdf', 'Устав-2.pdf')])->status());
        $new = array_values(array_filter($this->items('documents'), static fn (array $d): bool => $d['id'] === $doc['id']))[0]['file'];
        $this->assertNotSame($doc['file']['url'], $new['url']);
        $this->assertSame('Устав-2.pdf', $new['originalName']);
        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($this->uploadsDir . '/docs/' . basename($new['url']));
    }

    public function test_failed_save_keeps_old_file(): void
    {
        $this->save('documents', 'new', $this->doc(), ['file' => $this->upload('real.pdf', 'Устав.pdf')]);
        $doc = array_values(array_filter($this->items('documents'), static fn (array $d): bool => $d['title'] === 'Устав лагеря'))[0];
        $before = $this->uploadedFiles();

        $res = $this->save('documents', $doc['id'], $this->doc(['title' => '']), ['file' => $this->upload('real.pdf', 'Новый.pdf')]);
        $this->assertSame(422, $res->status());
        $this->assertSame($before, $this->uploadedFiles(), 'старый на месте, нового нет');

        $res = $this->save('documents', $doc['id'], $this->doc(), ['file' => $this->upload('fake.pdf', 'Новый.pdf')]);
        $this->assertSame(422, $res->status());
        $this->assertSame($before, $this->uploadedFiles());
        $this->assertSame($doc['file'], array_values(array_filter($this->items('documents'), static fn (array $d): bool => $d['id'] === $doc['id']))[0]['file']);
    }

    public function test_document_list_shows_section_date_size_and_pdf(): void
    {
        $this->save('documents', 'new', $this->doc(), ['file' => $this->upload('real.pdf', 'Устав.pdf')]);
        $body = AdminHttp::get($this->app, '/admin/documents/')->body();
        $this->assertStringContainsString('Учредительные документы', $body);
        $this->assertStringContainsString('01.05.2026', $body);
        $this->assertMatchesRegularExpression('#PDF, \d+(\s|&nbsp;|\x{00A0})КБ#u', $body);
    }

    public function test_document_editor_shows_current_file_name_size_open_and_replace(): void
    {
        $doc = $this->items('documents')[0];
        $body = AdminHttp::get($this->app, '/admin/documents/' . $doc['id'] . '/')->body();
        $this->assertStringContainsString($doc['file']['originalName'], $body);
        $this->assertStringContainsString('МБ', $body);
        $this->assertStringContainsString('Открыть', $body);
        $this->assertStringContainsString('Заменить', $body);
        $this->assertStringContainsString('href="' . $doc['file']['url'] . '"', $body);
    }

    // ---------- фото и подпись ----------

    public function test_image_requires_alt(): void
    {
        $res = $this->save('team', 'new', $this->member(), ['photo' => $this->upload('small.jpg', 'a.jpg')]);
        $this->assertSame(422, $res->status());
        $this->assertStringContainsString(self::ALT_MESSAGE, $res->body());
        $this->assertSame([], $this->uploadedFiles(), 'до подписи фото не принимается');

        $res = $this->save('team', 'new', $this->member(['photo__alt' => '   ']), ['photo' => $this->upload('small.jpg', 'a.jpg')]);
        $this->assertSame(422, $res->status());

        $res = $this->save('team', 'new', $this->member(['photo__alt' => 'Ольга с отрядом у костра']), ['photo' => $this->upload('small.jpg', 'a.jpg')]);
        $this->assertSame(303, $res->status());
        $m = array_values(array_filter($this->items('team'), static fn (array $t): bool => $t['name'] === 'Тестова Ольга Ивановна'))[0];
        $this->assertSame('Ольга с отрядом у костра', $m['photo']['alt']);
    }

    public function test_alt_is_not_needed_without_photo_and_typed_alt_survives_422(): void
    {
        $this->assertSame(303, $this->save('team', 'new', $this->member())->status());

        $res = $this->save('team', 'new', $this->member(['name' => '', 'photo__alt' => 'Подпись, которую нельзя потерять']), ['photo' => $this->upload('small.jpg', 'a.jpg')]);
        $this->assertSame(422, $res->status());
        $this->assertStringContainsString('Подпись, которую нельзя потерять', $res->body());
    }

    public function test_alt_is_escaped_and_cleaned(): void
    {
        $this->save('team', 'new', $this->member(['photo__alt' => "<b>x</b>\n\"y\""]), ['photo' => $this->upload('small.jpg', 'a.jpg')]);
        $m = array_values(array_filter($this->items('team'), static fn (array $t): bool => $t['name'] === 'Тестова Ольга Ивановна'))[0];
        $this->assertStringNotContainsString("\n", $m['photo']['alt']);
        $body = AdminHttp::get($this->app, '/admin/team/' . $m['id'] . '/')->body();
        $this->assertStringNotContainsString('<b>x</b>', $body);
    }

    public function test_alt_comes_from_the_alt_field_never_from_client_descriptor(): void
    {
        $evil = ['src' => '/uploads/img/../../x', 'widths' => [1], 'formats' => ['jpg'], 'w' => 1, 'h' => 1, 'alt' => 'чужое'];
        $this->save('team', 'new', $this->member(['photo' => $evil, 'photo__alt' => 'Своё']), ['photo' => $this->upload('small.jpg', 'a.jpg')]);
        $m = array_values(array_filter($this->items('team'), static fn (array $t): bool => $t['name'] === 'Тестова Ольга Ивановна'))[0];
        $this->assertSame('Своё', $m['photo']['alt']);
        $this->assertMatchesRegularExpression('#^/uploads/img/[0-9a-f]{16}$#', $m['photo']['src']);

        // «оставить» подпись можно менять, а дескриптор из запроса по-прежнему не принимается
        $this->save('team', $m['id'], $this->member(['photo' => $evil, 'photo__action' => 'keep', 'photo__alt' => 'Новая подпись']));
        $again = array_values(array_filter($this->items('team'), static fn (array $t): bool => $t['id'] === $m['id']))[0];
        $this->assertSame('Новая подпись', $again['photo']['alt']);
        $this->assertSame($m['photo']['src'], $again['photo']['src']);
    }

    public function test_kept_uploaded_photo_still_needs_alt(): void
    {
        $this->save('team', 'new', $this->member(['photo__alt' => 'Ольга']), ['photo' => $this->upload('small.jpg', 'a.jpg')]);
        $m = array_values(array_filter($this->items('team'), static fn (array $t): bool => $t['name'] === 'Тестова Ольга Ивановна'))[0];
        $res = $this->save('team', $m['id'], $this->member(['photo__alt' => '']));
        $this->assertSame(422, $res->status());
        $this->assertStringContainsString(self::ALT_MESSAGE, $res->body());
    }

    // ---------- галерея баз ----------

    /** @return array<string,mixed> */
    private function venue(): array
    {
        return $this->items('venues')[0];
    }

    private function venuePost(array $over = []): array
    {
        $v = $this->venue();

        return $over + ['slug' => $v['slug'], 'name' => $v['name'], 'address' => $v['address']];
    }

    /** Метка фото в форме: ключ библиотеки или имя загруженного файла. */
    private function token(mixed $photo): string
    {
        return is_string($photo) ? $photo : basename($photo['src']);
    }

    /** @param list<mixed> $photos @return list<string> */
    private function tokens(array $photos): array
    {
        return array_map(fn ($p): string => $this->token($p), $photos);
    }

    /** @return list<mixed> */
    private function photos(string $id): array
    {
        foreach ($this->items('venues') as $v) {
            if ($v['id'] === $id) {
                return $v['photos'];
            }
        }
        $this->fail('нет базы');
    }

    public function test_gallery_keeps_site_photos_when_nothing_changes(): void
    {
        $v = $this->venue();
        $this->assertSame(303, $this->save('venues', $v['id'], $this->venuePost())->status());
        $this->assertSame($v['photos'], $this->photos($v['id']));
    }

    public function test_gallery_add_photo_with_alt(): void
    {
        $v = $this->venue();
        $n = count($v['photos']);
        $res = $this->save('venues', $v['id'], $this->venuePost(['photos__newalt_1' => 'Новый корпус']), ['photos__new_1' => $this->upload('small.jpg', 'a.jpg')]);
        $this->assertSame(303, $res->status());
        $photos = $this->photos($v['id']);
        $this->assertCount($n + 1, $photos);
        $this->assertSame($v['photos'], array_slice($photos, 0, $n));
        $this->assertSame('Новый корпус', $photos[$n]['alt']);
        $this->assertMatchesRegularExpression('#^/uploads/img/[0-9a-f]{16}$#', $photos[$n]['src']);
        $this->assertNotEmpty($this->uploadedFiles());
    }

    public function test_gallery_new_photo_needs_alt_and_nothing_is_uploaded(): void
    {
        $v = $this->venue();
        $res = $this->save('venues', $v['id'], $this->venuePost(), ['photos__new_1' => $this->upload('small.jpg', 'a.jpg')]);
        $this->assertSame(422, $res->status());
        $this->assertStringContainsString(self::ALT_MESSAGE, $res->body());
        $this->assertSame([], $this->uploadedFiles());
        $this->assertSame($v['photos'], $this->photos($v['id']));
    }

    public function test_gallery_remove_site_key_and_uploaded_photo(): void
    {
        $v = $this->venue();
        $n = count($v['photos']);
        $this->save('venues', $v['id'], $this->venuePost(['photos__newalt_1' => 'Корпус']), ['photos__new_1' => $this->upload('small.jpg', 'a.jpg')]);
        $this->assertNotEmpty($this->uploadedFiles());

        $now = $this->photos($v['id']);
        $this->assertCount($n + 1, $now);
        $tokens = $this->tokens($now);
        $res = $this->save('venues', $v['id'], $this->venuePost(['photos__old' => $tokens, 'photos__del_' . $tokens[0] => '1', 'photos__del_' . $tokens[$n] => '1', 'photos__alt_' . $tokens[$n] => 'Корпус']));
        $this->assertSame(303, $res->status());
        $photos = $this->photos($v['id']);
        $this->assertSame(array_slice($v['photos'], 1), $photos);
        $this->assertSame([], $this->uploadedFiles(), 'файлы убранного фото удалены после сохранения');
    }

    public function test_gallery_reorder_by_posted_order(): void
    {
        $v = $this->venue();
        $n = count($v['photos']);
        $order = $this->tokens($v['photos']);
        [$order[0], $order[1]] = [$order[1], $order[0]];
        $res = $this->save('venues', $v['id'], $this->venuePost(['photos__old' => $order]));
        $this->assertSame(303, $res->status());
        $photos = $this->photos($v['id']);
        $this->assertSame($v['photos'][1], $photos[0]);
        $this->assertSame($v['photos'][0], $photos[1]);
        $this->assertSame(array_slice($v['photos'], 2), array_slice($photos, 2));
    }

    public function test_gallery_ignores_forged_indexes_duplicates_and_client_descriptors(): void
    {
        $v = $this->venue();
        $evil = ['src' => '/uploads/img/../../x', 'widths' => [1], 'formats' => ['jpg'], 'w' => 1, 'h' => 1];
        $res = $this->save('venues', $v['id'], $this->venuePost(['photos' => [$evil], 'photos__old' => [$this->token($v['photos'][0]), $this->token($v['photos'][0]), 'nope', '../x', '', $this->token($v['photos'][1])]]));
        $this->assertSame(303, $res->status());
        $photos = $this->photos($v['id']);
        $this->assertCount(count($v['photos']), $photos, 'ничего не потеряно и не добавлено');
        $this->assertSame($v['photos'][0], $photos[0]);
        $this->assertSame($v['photos'][1], $photos[1]);
        $this->assertNotContains($evil, $photos);
    }

    public function test_gallery_uploaded_photo_alt_is_required_and_editable(): void
    {
        $v = $this->venue();
        $n = count($v['photos']);
        $this->save('venues', $v['id'], $this->venuePost(['photos__newalt_1' => 'Корпус']), ['photos__new_1' => $this->upload('small.jpg', 'a.jpg')]);
        $tokens = $this->tokens($this->photos($v['id']));
        $res = $this->save('venues', $v['id'], $this->venuePost(['photos__old' => $tokens]));
        $this->assertSame(422, $res->status());
        $this->assertStringContainsString(self::ALT_MESSAGE, $res->body());

        $res = $this->save('venues', $v['id'], $this->venuePost(['photos__old' => $tokens, 'photos__alt_' . $tokens[$n] => 'Другая подпись']));
        $this->assertSame(303, $res->status());
        $this->assertSame('Другая подпись', $this->photos($v['id'])[$n]['alt']);
    }

    public function test_deleting_venue_removes_gallery_files(): void
    {
        $v = $this->venue();
        $this->save('venues', $v['id'], $this->venuePost(['photos__newalt_1' => 'Корпус']), ['photos__new_1' => $this->upload('small.jpg', 'a.jpg')]);
        $this->assertNotEmpty($this->uploadedFiles());
        AdminHttp::postWithToken($this->app, '/admin/venues/' . $v['id'] . '/delete/', ['confirm' => '1']);
        $this->assertSame([], $this->uploadedFiles());
    }

    public function test_gallery_editor_lists_photos_with_controls(): void
    {
        $v = $this->venue();
        $body = AdminHttp::get($this->app, '/admin/venues/' . $v['id'] . '/')->body();
        $first = $this->token($v['photos'][0]);
        $this->assertStringContainsString('name="photos__old[]" value="' . $first . '"', $body);
        $this->assertStringContainsString('name="photos__del_' . $first . '"', $body);
        $this->assertStringContainsString('name="photos__new_1"', $body);
        $this->assertStringContainsString('name="photos__newalt_1"', $body);
        $this->assertStringContainsString('data-gallery-up', $body);
        $this->assertStringContainsString('data-gallery-down', $body);
    }

    // ---------- редактор базы: подписи и группы ----------

    public function test_venue_editor_groups_and_labels_from_order201(): void
    {
        $v = $this->venue();
        $body = AdminHttp::get($this->app, '/admin/venues/' . $v['id'] . '/')->body();

        $this->assertStringContainsString('Материально-техническое обеспечение (п. 9)', $body);
        $this->assertStringContainsString('Доступная среда (п. 11)', $body);
        $this->assertLessThan(strpos($body, 'Доступная среда (п. 11)'), strpos($body, 'Материально-техническое обеспечение (п. 9)'));
        $this->assertStringContainsString('9 б) Условия проживания', $body);
        $this->assertStringContainsString('11 ж) Сопровождение ассистентом', $body);
        $this->assertStringContainsString('11 н) Собака-проводник', $body);
        // полная формулировка приказа — под подписью, свёрнутая
        $this->assertMatchesRegularExpression('#<details[^>]*>\s*<summary[^>]*>[^<]*</summary>s*<p>[^<]*об условиях проживания детей в Организации отдыха#u', $body);
        // «необязательно» — у пустых необязательных пунктов (9 д и 11 е)
        $empty = AdminHttp::get($this->app, '/admin/venues/new/')->body();
        $this->assertSame(2, substr_count($empty, 'необязательно'));
        $this->assertMatchesRegularExpression('#9 д\) [^<]*<span class="opt">необязательно#u', $empty);
        $this->assertMatchesRegularExpression('#11 е\) [^<]*<span class="opt">необязательно#u', $empty);
    }

    public function test_venue_fields_are_still_saved_by_key(): void
    {
        $v = $this->venue();
        $this->assertSame(303, $this->save('venues', $v['id'], $this->venuePost(['mto_b' => 'Новые условия', 'access_zh' => 'Ассистент рядом']))->status());
        $saved = $this->venue();
        $this->assertSame('Новые условия', $saved['mto_b']);
        $this->assertSame('Ассистент рядом', $saved['access_zh']);
    }

    // ---------- редактор команды ----------

    public function test_team_editor_layout(): void
    {
        $m = $this->items('team')[0];
        $body = AdminHttp::get($this->app, '/admin/team/' . $m['id'] . '/')->body();

        $this->assertMatchesRegularExpression('#type="radio" name="group" value="pedagogue"#', $body);
        $this->assertStringContainsString('Для раздела «Сведения» (п. 6)', $body);
        $this->assertStringContainsString('Обязательно для педагогов и вожатых: должность и уровень образования', $body);
        $this->assertLessThan(strpos($body, 'name="education"'), strpos($body, 'Для раздела «Сведения» (п. 6)'));
        $this->assertStringContainsString('name="ovzResponsible"', $body);
        $this->assertStringContainsString('role="switch"', $body);
    }

    public function test_ovz_responsible_listed(): void
    {
        $this->assertSame(303, $this->save('team', 'new', $this->member(['ovzResponsible' => '1']))->status());
        $html = TestApp::get($this->app, '/svedeniya/ob-organizacii/')->body();
        $at = strpos($html, 'sostav.b');
        $this->assertNotFalse($at, 'нет п. 6 б');
        $this->assertStringContainsString('Тестова Ольга Ивановна', $this->plain(substr($html, $at, 2500)));
    }

    // ---------- скрытие и показ ----------

    public function test_hidden_vacancy_not_public(): void
    {
        $v = $this->items('vacancies')[0];
        $this->assertStringContainsString($v['title'], TestApp::get($this->app, '/vacancies/')->body());

        $this->save('vacancies', $v['id'], ['title' => $v['title'], 'summary' => $v['summary'], 'conditions' => $v['conditions'], 'hidden' => '1']);
        $this->assertStringNotContainsString($v['title'], TestApp::get($this->app, '/vacancies/')->body());
    }

    public function test_visibility_toggle_hides_and_shows(): void
    {
        $v = $this->items('vacancies')[0];
        $res = AdminHttp::postWithToken($this->app, '/admin/vacancies/' . $v['id'] . '/visibility/', ['state' => 'hide']);
        $this->assertSame(303, $res->status());
        $this->assertSame(true, $this->items('vacancies')[0]['hidden']);
        $this->assertStringNotContainsString($v['title'], TestApp::get($this->app, '/vacancies/')->body());
        $this->assertContains('vacancies.visibility', array_column($this->app->audit()->recent(50), 'action'));

        AdminHttp::postWithToken($this->app, '/admin/vacancies/' . $v['id'] . '/visibility/', ['state' => 'show']);
        $this->assertSame(false, $this->items('vacancies')[0]['hidden']);
    }

    public function test_visibility_toggle_for_reviews_uses_visible(): void
    {
        $r = $this->items('reviews')[0];
        AdminHttp::postWithToken($this->app, '/admin/reviews/' . $r['id'] . '/visibility/', ['state' => 'hide']);
        $this->assertSame(false, $this->items('reviews')[0]['visible']);
        AdminHttp::postWithToken($this->app, '/admin/reviews/' . $r['id'] . '/visibility/', ['state' => 'show']);
        $this->assertSame(true, $this->items('reviews')[0]['visible']);
    }

    public function test_visibility_toggle_needs_csrf_valid_state_and_a_field(): void
    {
        $v = $this->items('vacancies')[0];
        $hash = $this->hash('vacancies');
        $this->assertSame(419, AdminHttp::post($this->app, '/admin/vacancies/' . $v['id'] . '/visibility/', ['state' => 'hide'])->status());
        $this->assertSame(422, AdminHttp::postWithToken($this->app, '/admin/vacancies/' . $v['id'] . '/visibility/', ['state' => 'maybe'])->status());
        $this->assertSame(404, AdminHttp::postWithToken($this->app, '/admin/vacancies/aaaaaaaaaaaa/visibility/', ['state' => 'hide'])->status());
        $this->assertSame($hash, $this->hash('vacancies'));

        $faq = $this->items('faq')[0];
        $this->assertSame(404, AdminHttp::postWithToken($this->app, '/admin/faq/' . $faq['id'] . '/visibility/', ['state' => 'hide'])->status(), 'у вопросов нет скрытия');
    }

    public function test_lists_show_visibility_buttons_only_where_a_hide_field_exists(): void
    {
        foreach (['venues' => false, 'team' => true, 'documents' => false, 'vacancies' => true, 'faq' => false, 'reviews' => true, 'shifts' => true] as $name => $has) {
            $body = AdminHttp::get($this->app, "/admin/$name/")->body();
            $this->assertSame($has, str_contains($body, '/visibility/'), $name);
        }
        $body = AdminHttp::get($this->app, '/admin/vacancies/')->body();
        $this->assertStringContainsString('name="_csrf"', $body);
        $this->assertStringContainsString('Скрыть', $body);
    }

    public function test_hidden_row_offers_show_button(): void
    {
        $v = $this->items('vacancies')[0];
        AdminHttp::postWithToken($this->app, '/admin/vacancies/' . $v['id'] . '/visibility/', ['state' => 'hide']);
        $body = AdminHttp::get($this->app, '/admin/vacancies/')->body();
        $this->assertStringContainsString('Показать', $body);
        $this->assertStringContainsString('Скрыто', $body);
    }

    // ---------- вопросы и отзывы ----------

    public function test_faq_list_shows_home_counter(): void
    {
        $onHome = count(array_filter($this->items('faq'), static fn (array $f): bool => !empty($f['onHome'])));
        $body = AdminHttp::get($this->app, '/admin/faq/')->body();
        $this->assertStringContainsString('На главной: ' . $onHome . ' из 5', $body);
        $this->assertStringNotContainsString('На главной:', AdminHttp::get($this->app, '/admin/reviews/')->body());
    }

    public function test_faq_and_reviews_editors_have_switches(): void
    {
        $faq = $this->items('faq')[0];
        $this->assertStringContainsString('name="onHome"', AdminHttp::get($this->app, '/admin/faq/' . $faq['id'] . '/')->body());
        $r = $this->items('reviews')[0];
        $this->assertStringContainsString('name="visible"', AdminHttp::get($this->app, '/admin/reviews/' . $r['id'] . '/')->body());
    }

    public function test_reviews_switch_saves_visible(): void
    {
        $r = $this->items('reviews')[0];
        $this->save('reviews', $r['id'], ['text' => $r['text'], 'author' => $r['author']]);
        $this->assertSame(false, $this->items('reviews')[0]['visible']);
        $this->save('reviews', $r['id'], ['text' => $r['text'], 'author' => $r['author'], 'visible' => '1']);
        $this->assertSame(true, $this->items('reviews')[0]['visible']);
    }
}
