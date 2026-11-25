<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Admin;

use Groza\Admin\ImageProcessor;
use Groza\Admin\UploadException;
use PHPUnit\Framework\TestCase;

final class ImageProcessorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/groza-img-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->dir);
    }

    private function fx(string $name): string
    {
        return dirname(__DIR__, 3) . '/fixtures/uploads/' . $name;
    }

    /** @return array{src:string,widths:list<int>,formats:list<string>,w:int,h:int} */
    private function proc(string $fixture, ?ImageProcessor $p = null, string $path = ''): array
    {
        return ($p ?? new ImageProcessor())->process($path !== '' ? $path : $this->fx($fixture), $this->dir, 'abcd1234abcd1234');
    }

    public function test_exif_and_gps_removed(): void
    {
        $input = file_get_contents($this->fx('gps.jpg'));
        $this->assertStringContainsString('Exif', $input, 'фикстура должна содержать EXIF');
        $this->assertTrue(isset(exif_read_data($this->fx('gps.jpg'), null, true)['GPS']), 'фикстура должна содержать GPS');

        $d = $this->proc('gps.jpg');

        foreach ($d['widths'] as $w) {
            $jpg = file_get_contents("{$this->dir}/abcd1234abcd1234-$w.jpg");
            $this->assertStringNotContainsString("Exif\0\0", $jpg);
            $this->assertStringNotContainsString('GrozaTestCam', $jpg);
            $webp = file_get_contents("{$this->dir}/abcd1234abcd1234-$w.webp");
            $this->assertStringNotContainsString('EXIF', $webp);
            $this->assertStringNotContainsString('GrozaTestCam', $webp);
        }
    }

    public function test_orientation_applied(): void
    {
        $d = $this->proc('rotated.jpg');
        $this->assertSame(200, $d['w']);
        $this->assertSame(300, $d['h']);
        $this->assertSame([200, 300], array_slice(getimagesize("{$this->dir}/abcd1234abcd1234-200.jpg"), 0, 2));
    }

    public function test_widths_not_upscaled(): void
    {
        $d = $this->proc('small.jpg');
        $this->assertSame([400], $d['widths']);
        $this->assertSame(['webp', 'jpg'], $d['formats']);
        $this->assertSame(['w' => 400, 'h' => 300], ['w' => $d['w'], 'h' => $d['h']]);
        $this->assertSame(['abcd1234abcd1234-400.jpg', 'abcd1234abcd1234-400.webp'], $this->files());
    }

    public function test_large_source_gets_all_widths_with_original_between(): void
    {
        $d = $this->proc('gps.jpg'); // 1200 px
        $this->assertSame([480, 960, 1200], $d['widths']);
        $this->assertSame(800, $d['h']);
    }

    public function test_pixel_guard_rejects_before_decoding(): void
    {
        try {
            $this->proc('small.jpg', new ImageProcessor(maxPixels: 10000));
            $this->fail('нужно исключение');
        } catch (UploadException $e) {
            $this->assertStringStartsWith('Фото слишком большое: уменьшите до ', $e->getMessage());
        }
        $this->assertSame([], $this->files());
    }

    public function test_bomb_png_rejected_and_no_files_left(): void
    {
        try {
            $this->proc('bomb.png');
            $this->fail();
        } catch (UploadException $e) {
            $this->assertStringStartsWith('Фото слишком большое', $e->getMessage());
        }
        $this->assertSame([], $this->files());
    }

    public function test_unreadable_image_rejected(): void
    {
        $this->expectException(UploadException::class);
        $this->proc('fake.jpg');
    }

    public function test_png_alpha_flattened_on_white_for_jpeg_and_kept_in_webp(): void
    {
        $this->proc('photo.png');
        $jpg = imagecreatefromjpeg("{$this->dir}/abcd1234abcd1234-480.jpg");
        $c = imagecolorsforindex($jpg, imagecolorat($jpg, 10, 10));
        $this->assertGreaterThan(240, $c['red']);
        $this->assertGreaterThan(90, $c['green']); // красный 50% на белом — розовый, не чёрный
        $webp = imagecreatefromwebp("{$this->dir}/abcd1234abcd1234-480.webp");
        $this->assertGreaterThan(0, imagecolorsforindex($webp, imagecolorat($webp, 10, 10))['alpha']);
    }

    public function test_php_payload_appended_to_jpeg_is_stripped(): void
    {
        $evil = $this->dir . '/evil-src.jpg';
        file_put_contents($evil, file_get_contents($this->fx('small.jpg')) . "\n<?php system(\$_GET['c']); ?>");
        $this->proc('', null, $evil);
        foreach ($this->files() as $f) {
            $this->assertStringNotContainsString('<?php', file_get_contents($this->dir . '/' . $f), $f);
            $this->assertStringNotContainsString('system(', file_get_contents($this->dir . '/' . $f), $f);
        }
        unlink($evil);
    }

    /** @return list<string> */
    private function files(): array
    {
        $out = array_map('basename', glob($this->dir . '/abcd*') ?: []);
        sort($out);

        return $out;
    }
}
