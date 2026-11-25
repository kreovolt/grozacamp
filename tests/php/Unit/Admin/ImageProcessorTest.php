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

    public function test_rotation_needs_bigger_memory_budget_than_upright(): void
    {
        $mb128 = 128 * 1048576;
        $p = new ImageProcessor(memoryLimit: $mb128);
        $this->assertSame(20132659, $p->effectiveMaxPixels());      // 5 байт/пиксель
        $this->assertSame(11184810, $p->effectiveMaxPixels(true));  // 9 байт/пиксель (imagerotate)
        // поднять память не вышло — бюджет прежний; вышло — 25 Мп проходят и с поворотом
        $this->assertSame(25000000, (new ImageProcessor(memoryLimit: $mb128, raiseMemory: static fn (int $c): int => 256 * 1048576))->effectiveMaxPixels());
        $this->assertSame(25000000, (new ImageProcessor(memoryLimit: $mb128, raiseMemory: static fn (int $c): int => 256 * 1048576))->effectiveMaxPixels(true));
        $this->assertSame(25000000, (new ImageProcessor(memoryLimit: -1))->effectiveMaxPixels(true));
    }

    public function test_rotated_photo_between_the_two_budgets_rejected_with_nine_byte_budget(): void
    {
        // 18 Мп, Orientation 6: для 128 МБ без поворота ещё можно (20 Мп), с поворотом — нет (11 Мп)
        $p = new ImageProcessor(memoryLimit: 128 * 1048576);
        try {
            $p->process($this->fx('wide-rot6.jpg'), $this->dir, 'abcd1234abcd1234');
            $this->fail('нужно исключение');
        } catch (UploadException $e) {
            $this->assertSame('Фото слишком большое: уменьшите до 11 мегапикселей', $e->getMessage());
        }
        $this->assertSame([], $this->files());
    }

    public function test_small_budget_arithmetic_and_upright_passes(): void
    {
        // 60000 пикселей: лимит подобран так, что по 5 байт/пиксель хватает (80000), а по 9 — нет (44444)
        $limit = 33954432;
        $this->assertSame(80000, (new ImageProcessor(memoryLimit: $limit))->effectiveMaxPixels());
        $this->assertSame(44444, (new ImageProcessor(memoryLimit: $limit))->effectiveMaxPixels(true));
        $d = (new ImageProcessor(memoryLimit: 34300000))->process($this->fx('upright.jpg'), $this->dir, 'abcd1234abcd1234');
        $this->assertSame(300, $d['w']);
    }

    /** Узкая и высокая картинка: самая большая ширина равна исходной — копий в полный размер быть не должно. */
    private function tallFile(string $kind, int $w = 600, int $h = 4800): string
    {
        $path = $this->dir . "/tall-src.$kind";
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, 0x3366aa);
        $kind === 'jpg' ? imagejpeg($im, $path, 50) : imagepng($im, $path, 9);
        unset($im);

        return $path;
    }

    /** @return array{0:int,1:int} пик памяти в байтах и число пикселей */
    private function peakOf(string $path, int $limit): array
    {
        $before = memory_get_usage();
        memory_reset_peak_usage();
        (new ImageProcessor(memoryLimit: $limit))->process($path, $this->dir, 'abcd1234abcd1234');
        $peak = memory_get_peak_usage() - $before;
        [$w, $h] = getimagesize($path);

        return [$peak, $w * $h];
    }

    public function test_peak_memory_of_full_width_output_stays_within_the_estimate(): void
    {
        foreach (['jpg' => 10.5, 'png' => 16.0] as $kind => $factor) {
            $path = $this->tallFile($kind);
            [$peak, $n] = $this->peakOf($path, 512 * 1048576);
            $this->assertLessThanOrEqual($factor * $n + 2 * 1048576, $peak, "$kind: пик {$peak} Б на $n пикселей");
            unlink($path);
            foreach (glob($this->dir . '/abcd*') as $f) {
                unlink($f);
            }
        }
    }

    public function test_narrow_tall_photo_that_would_not_fit_is_rejected_gracefully(): void
    {
        $path = $this->tallFile('jpg'); // 2,88 Мп: нужно ≈ 10,5 Б/пикс = 30 МБ + 32 МБ запаса
        try {
            (new ImageProcessor(memoryLimit: 56 * 1048576))->process($path, $this->dir, 'abcd1234abcd1234');
            $this->fail('нужно исключение');
        } catch (UploadException $e) {
            $this->assertMatchesRegularExpression('/^Фото слишком большое: уменьшите до [1-9][0-9]* мегапикселей$/u', $e->getMessage());
        }
        $this->assertSame([], $this->files());
        $d = (new ImageProcessor(memoryLimit: 70 * 1048576))->process($path, $this->dir, 'abcd1234abcd1234');
        $this->assertSame([480, 600], $d['widths']);
        unlink($path);
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
