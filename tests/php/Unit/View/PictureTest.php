<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\View;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function Groza\View\image;
use function Groza\View\photo;
use function Groza\View\picture;

final class PictureTest extends TestCase
{
    /** @return array{src:string,widths:list<int>,formats:list<string>,w:int,h:int} */
    private function img(): array
    {
        return ['src' => '/assets/img/friends-selfie', 'widths' => [480, 960, 1440, 1920], 'formats' => ['avif', 'webp', 'jpg'], 'w' => 1920, 'h' => 1280];
    }

    public function testBuildsPictureWithSourcesInFormatOrder(): void
    {
        $html = picture($this->img(), 'Ребята у реки', '100vw');

        $avif = strpos($html, '<source type="image/avif"');
        $webp = strpos($html, '<source type="image/webp"');
        $this->assertNotFalse($avif);
        $this->assertNotFalse($webp);
        $this->assertLessThan($webp, $avif);
        $this->assertStringContainsString('/assets/img/friends-selfie-480.avif 480w', $html);
        $this->assertStringContainsString('/assets/img/friends-selfie-1920.webp 1920w', $html);
        $this->assertStringContainsString('sizes="100vw"', $html);
        $this->assertStringContainsString(
            '<img src="/assets/img/friends-selfie-960.jpg" srcset="', $html);
        $this->assertStringContainsString('width="1920" height="1280" loading="lazy" decoding="async" alt="Ребята у реки">', $html);
        $this->assertStringContainsString('/assets/img/friends-selfie-1920.jpg 1920w', $html);
    }

    public function testEagerImageGetsHighPriority(): void
    {
        $html = picture($this->img(), 'x', '100vw', true);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertStringContainsString('loading="eager"', $html);
        $this->assertStringNotContainsString('loading="lazy"', $html);
        $this->assertStringNotContainsString('fetchpriority', picture($this->img(), 'x'));
    }

    public function testSkipsFormatsNotInDescriptor(): void
    {
        $html = picture([...$this->img(), 'formats' => ['webp', 'jpg']], 'x');
        $this->assertStringNotContainsString('image/avif', $html);
        $this->assertStringContainsString('image/webp', $html);
    }

    public function testJpgOnlyHasNoSourceElements(): void
    {
        $html = picture([...$this->img(), 'formats' => ['jpg']], 'x');
        $this->assertStringNotContainsString('<source', $html);
        $this->assertStringContainsString('<img', $html);
    }

    public function testEscapesAltAndClass(): void
    {
        $html = picture($this->img(), '"x"', '100vw', false, 'a"b');
        $this->assertStringContainsString('alt="&quot;x&quot;"', $html);
        $this->assertStringContainsString('class="a&quot;b"', $html);
        $this->assertStringNotContainsString('class=""', picture($this->img(), 'x'));
    }

    public function testFallbackIsSecondWidth(): void
    {
        $img = [...$this->img(), 'widths' => [480, 960, 1440]];
        $this->assertStringContainsString('<img src="/assets/img/friends-selfie-960.jpg"', picture($img, 'x'));
        $img = [...$this->img(), 'widths' => [480]];
        $this->assertStringContainsString('<img src="/assets/img/friends-selfie-480.jpg"', picture($img, 'x'));
    }

    public function testPhotoReadsManifestAndThrowsOnUnknownKey(): void
    {
        $d = photo('friends-selfie');
        $this->assertSame('/assets/img/friends-selfie', $d['src']);
        $this->assertSame([480, 960, 1440, 1920], $d['widths']);
        $this->assertContains('avif', $d['formats']);
        $this->assertGreaterThan(0, $d['w']);
        $this->assertGreaterThan(0, $d['h']);
        $this->assertFileExists(dirname(__DIR__, 4) . '/public' . $d['src'] . '-960.jpg');

        $this->expectException(InvalidArgumentException::class);
        photo('no-such-photo');
    }

    public function testImageResolvesKeyDescriptorAndNull(): void
    {
        $this->assertSame(photo('base-cabins'), image('base-cabins'));
        $this->assertSame($this->img(), image($this->img()));
        $this->assertNull(image(null));
    }
}
