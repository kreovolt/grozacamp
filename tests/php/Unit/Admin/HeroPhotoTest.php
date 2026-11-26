<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Admin;

use Groza\Admin\Controllers\CollectionController;
use Groza\Admin\Controllers\TextsController;
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

/** Спека §7.1 / §10.2: фото героя главной можно заменить в админке («Главная» → «Фото первого экрана»). */
final class HeroPhotoTest extends TestCase
{
    private const KEY = 'home.heroPhoto';

    private App $app;
    private string $uploads;

    protected function setUp(): void
    {
        Session::reset();
        $this->uploads = sys_get_temp_dir() . '/groza-hero-' . bin2hex(random_bytes(6));
        mkdir($this->uploads, 0777, true);
        $this->app = TestApp::make(['uploadsDir' => $this->uploads]);
        $this->app->setUploads(new Uploads(
            $this->uploads,
            '/uploads',
            new ImageProcessor(),
            isUploaded: static fn (string $f): bool => true,
            move: static fn (string $a, string $b): bool => copy($a, $b),
            quarantineDir: $this->app->quarantineDir(),
        ));
        AdminHttp::user($this->app);
        AdminHttp::login($this->app);
    }

    protected function tearDown(): void
    {
        Session::reset();
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->uploads, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $i) {
            $i->isDir() ? rmdir($i->getPathname()) : unlink($i->getPathname());
        }
        rmdir($this->uploads);
    }

    /** @return array<string,mixed> */
    private function file(string $fixture = 'small.jpg', string $name = 'hero.jpg'): array
    {
        $src = dirname(__DIR__, 3) . '/fixtures/uploads/' . $fixture;

        return ['name' => $name, 'type' => '', 'tmp_name' => $src, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($src)];
    }

    /** @param array<string,mixed> $extra @param array<string,mixed> $files */
    private function save(array $extra, array $files = []): Response
    {
        $post = ['_csrf' => Csrf::token(), '_hash' => $this->app->store()->hash('texts'), 'text' => ['home.heroTitle' => 'Лагерь настоящих приключений']] + $extra;

        return $this->app->handle(Request::create('POST', '/admin/texts/home/', [], $post, [], $files));
    }

    private function hero(): string
    {
        $html = TestApp::get($this->app, '/')->body();
        $this->assertSame(1, preg_match('#<section[^>]*data-block="hero".*?</section>#s', $html, $m));

        return $m[0];
    }

    /** @return array<string,mixed>|null */
    private function stored(): ?array
    {
        return $this->app->store()->read('texts')[self::KEY] ?? null;
    }

    public function test_form_shows_photo_field_with_default_and_help(): void
    {
        $body = AdminHttp::get($this->app, '/admin/texts/home/')->body();

        $this->assertStringContainsString('enctype="multipart/form-data"', $body);
        $this->assertStringContainsString('id="home.heroPhoto"', $body);
        $this->assertStringContainsString('name="' . TextsController::fileField(self::KEY) . '"', $body);
        $this->assertStringContainsString('name="imgalt[home.heroPhoto]"', $body);
        $this->assertStringContainsString('Сейчас на сайте фото по умолчанию', $body);
        $this->assertStringContainsString('Картинка для соцсетей', $body, 'OG-картинка не меняется — сказано в подсказке');
        $this->assertStringContainsString('/assets/img/friends-selfie-', $body, 'миниатюра фото по умолчанию');
    }

    public function test_upload_with_alt_replaces_the_hero_and_keeps_eager_loading(): void
    {
        $res = $this->save(['imgalt' => [self::KEY => 'Ребята у палаток на рассвете']], [TextsController::fileField(self::KEY) => $this->file()]);
        $this->assertSame(303, $res->status());

        $d = $this->stored();
        $this->assertIsArray($d);
        $this->assertMatchesRegularExpression('#^/uploads/img/[0-9a-f]{16}$#', $d['src']);
        $this->assertSame('Ребята у палаток на рассвете', $d['alt']);
        $this->assertNotEmpty(glob($this->uploads . '/img/' . basename($d['src']) . '-*') ?: []);

        $hero = $this->hero();
        $this->assertSame(1, preg_match_all('#<img\b[^>]*>#', $hero, $imgs), 'в герое одно фото');
        $this->assertMatchesRegularExpression('#\ssrc="' . preg_quote($d['src'], '#') . '-[0-9]+\.jpg"#', $imgs[0][0]);
        $this->assertStringContainsString('loading="eager" fetchpriority="high"', $imgs[0][0]);
        $this->assertStringContainsString('alt="Ребята у палаток на рассвете"', $imgs[0][0]);
        $this->assertStringNotContainsString('friends-selfie', $hero);

        // картинка для соцсетей — прежняя, статичная
        $this->assertMatchesRegularExpression('#<meta property="og:image" content="[^"]*/assets/#', TestApp::get($this->app, '/')->body());
    }

    public function test_alt_is_required_and_nothing_is_stored_without_it(): void
    {
        $before = $this->app->store()->hash('texts');
        $res = $this->save(['imgalt' => [self::KEY => '  ']], [TextsController::fileField(self::KEY) => $this->file()]);

        $this->assertSame(422, $res->status());
        $this->assertStringContainsString(CollectionController::MSG_ALT, $res->body());
        $this->assertSame($before, $this->app->store()->hash('texts'));
        $this->assertSame([], glob($this->uploads . '/img/*') ?: [], 'файл не принят до проверки остального');
    }

    public function test_bad_file_is_a_field_error(): void
    {
        $res = $this->save(['imgalt' => [self::KEY => 'Фото']], [TextsController::fileField(self::KEY) => $this->file('fake.jpg', 'x.jpg')]);

        $this->assertSame(422, $res->status());
        $this->assertStringContainsString('Файл не похож на фото', $res->body());
        $this->assertNull($this->stored());
    }

    public function test_alt_can_be_edited_and_replace_moves_the_old_photo_off_the_site(): void
    {
        $this->save(['imgalt' => [self::KEY => 'Первое']], [TextsController::fileField(self::KEY) => $this->file()]);
        $first = $this->stored();

        $this->assertSame(303, $this->save(['imgalt' => [self::KEY => 'Первое, уточнённая подпись'], 'imgaction' => [self::KEY => 'keep']])->status());
        $this->assertSame($first['src'], $this->stored()['src']);
        $this->assertSame('Первое, уточнённая подпись', $this->stored()['alt']);

        $this->assertSame(303, $this->save(['imgalt' => [self::KEY => 'Второе']], [TextsController::fileField(self::KEY) => $this->file('upright.jpg', 'b.jpg')])->status());
        $this->assertNotSame($first['src'], $this->stored()['src']);
        $this->assertSame([], glob($this->uploads . '/img/' . basename($first['src']) . '-*') ?: [], 'прежнее фото — не на сайте');
        $this->assertNotEmpty(glob($this->app->quarantineDir() . '/img/' . basename($first['src']) . '-*') ?: [], 'но для отката сохранено');
    }

    public function test_remove_returns_the_default_photo(): void
    {
        $this->save(['imgalt' => [self::KEY => 'Своё']], [TextsController::fileField(self::KEY) => $this->file()]);
        $this->assertSame(303, $this->save(['imgalt' => [self::KEY => ''], 'imgaction' => [self::KEY => 'remove']])->status());

        $this->assertNull($this->stored());
        $hero = $this->hero();
        $this->assertMatchesRegularExpression('#<img[^>]*src="/assets/img/friends-selfie-[0-9]+\.jpg"[^>]*loading="eager" fetchpriority="high"#', $hero);
    }

    public function test_texts_without_the_photo_field_keep_the_photo(): void
    {
        $this->save(['imgalt' => [self::KEY => 'Своё']], [TextsController::fileField(self::KEY) => $this->file()]);
        $src = $this->stored()['src'];

        $this->assertSame(303, $this->save([])->status(), 'поля фото нет в отправке (старая вкладка) — фото не трогается');
        $this->assertSame($src, $this->stored()['src']);
    }

    /** @return array<string,array{0:mixed}> */
    public static function junk(): array
    {
        return [
            'library key' => ['friends-selfie'],
            'foreign url' => [['src' => 'https://evil.example/x', 'widths' => [480], 'formats' => ['jpg'], 'w' => 1, 'h' => 1]],
            'outside uploads' => [['src' => '/uploads/../config/x', 'widths' => [480], 'formats' => ['jpg'], 'w' => 1, 'h' => 1]],
            'no widths' => [['src' => '/uploads/img/abc', 'widths' => [], 'formats' => ['jpg'], 'w' => 1, 'h' => 1]],
            'bad format' => [['src' => '/uploads/img/abc', 'widths' => [480], 'formats' => ['"><x'], 'w' => 1, 'h' => 1]],
            'string widths' => [['src' => '/uploads/img/abc', 'widths' => ['480'], 'formats' => ['jpg'], 'w' => 1, 'h' => 1]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('junk')]
    public function test_invalid_stored_value_falls_back_to_the_default(mixed $value): void
    {
        $texts = $this->app->store()->read('texts');
        $texts[self::KEY] = $value;
        $this->app->store()->write('texts', $texts, 'test');

        $this->assertMatchesRegularExpression('#src="/assets/img/friends-selfie-[0-9]+\.jpg"[^>]*loading="eager" fetchpriority="high"#', $this->hero());
    }
}
